<?php

namespace App\Actions\Registration;

use App\Models\EmiSchedule;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Services\BinaryPlacementResolver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §3.1 steps 1-6 — validates the sponsor code, resolves
 * binary placement, creates the pending member + user login identity, the
 * EMI-schedule header (rate-booking selection stored per §3.0) for EMI
 * plans, and the registration Payment record. Stops at "pending
 * registration/order record before payment" (§3.1 step 6) — activation
 * (Customer ID, status=active) only happens once the payment is confirmed,
 * see ActivateMembershipOnPaymentConfirmed.
 *
 * Scope boundary (documented per the project's zero-rework discipline): this
 * action does NOT generate `emi_installments` rows — full EMI schedule
 * generation (all installments) is T-005's job. It only creates the
 * `emi_schedules` header row carrying the mandatory rate-booking selection,
 * because that selection must be captured and priced at registration time
 * (it drives the registration payment amount) even though T-005 hasn't
 * started yet.
 */
class RegisterMember
{
    private const MAX_PLACEMENT_RETRIES = 3;

    public function __construct(
        private readonly ValidateSponsorCode $validateSponsorCode,
        private readonly BinaryPlacementResolver $placementResolver,
        private readonly CalculateEmiRateBooking $calculateEmiRateBooking,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated RegisterMemberRequest input: sponsor_code,
     *                                      placement_side, mobile, email, name, membership_plan_id,
     *                                      rate_booking_method (EMI plans only), payment_mode.
     */
    public function __invoke(array $data): Member
    {
        $attempt = 0;

        while (true) {
            try {
                return $this->attempt($data);
            } catch (QueryException $e) {
                $isUniqueViolation = str_contains($e->getMessage(), 'members_placement_unique');
                if (! $isUniqueViolation || ++$attempt >= self::MAX_PLACEMENT_RETRIES) {
                    throw $e;
                }
                // Concurrent registration took the same placement slot — resolve again and retry.
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function attempt(array $data): Member
    {
        $sponsorCode = (string) $data['sponsor_code'];

        // RegisterMemberRequest already validates `in:left,right`; narrowed again here so
        // BinaryPlacementResolver gets the literal type it expects rather than plain string.
        $placementSide = match ($data['placement_side']) {
            'left' => 'left',
            'right' => 'right',
            default => throw ValidationException::withMessages(['placement_side' => 'Select Left or Right.']),
        };
        $membershipPlanId = (int) $data['membership_plan_id'];
        $rateBookingMethod = isset($data['rate_booking_method']) ? (string) $data['rate_booking_method'] : null;
        $paymentMode = (string) $data['payment_mode'];

        return DB::transaction(function () use ($data, $sponsorCode, $placementSide, $membershipPlanId, $rateBookingMethod, $paymentMode) {
            $sponsor = ($this->validateSponsorCode)($sponsorCode);

            $plan = MembershipPlan::where('id', $membershipPlanId)->where('is_active', true)->firstOrFail();

            $placement = $this->placementResolver->resolve($sponsor, $placementSide);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'mobile' => $data['mobile'],
                'password' => Str::random(40), // Real password (= Customer ID) is set at activation; member cannot log in before then anyway.
                'role' => 'member',
            ]);

            $member = Member::create([
                'user_id' => $user->id,
                'customer_id' => null,
                'sponsor_id' => $sponsor->id,
                'placement_parent_id' => $placement['parent_id'],
                'placement_side' => $placement['side'],
                'membership_plan_id' => $plan->id,
                'status' => 'draft',
            ]);

            if ($plan->isEmiPlan()) {
                if (! in_array($rateBookingMethod, ['current_rate', 'future_rate'], true)) {
                    throw ValidationException::withMessages([
                        'rate_booking_method' => 'Select Current Rate Booking or Future Rate Booking for this plan.',
                    ]);
                }

                $rateBooking = ($this->calculateEmiRateBooking)($plan, $rateBookingMethod);

                EmiSchedule::create([
                    'member_id' => $member->id,
                    'membership_plan_id' => $plan->id,
                    'total_installments' => $plan->installment_count,
                    'rate_booking_method' => $rateBookingMethod,
                    'installment_amount' => $rateBooking['installment_amount'],
                    'metal_rate_id' => $rateBooking['metal_rate_id'],
                    'rate_per_gram_at_booking' => $rateBooking['rate_per_gram'],
                    'fixed_weight_grams' => $rateBooking['fixed_weight_grams'],
                    'maintenance_cost' => $rateBooking['maintenance_cost'],
                    'rule_version_id' => $rateBooking['rule_version_id'],
                    'future_commitment_amount' => $rateBooking['future_commitment_amount'],
                ]);

                $registrationAmount = $rateBooking['installment_amount'];
            } else {
                $registrationAmount = $plan->amount;
            }

            $member->update(['status' => 'payment_pending']);

            Payment::create([
                'member_id' => $member->id,
                'type' => 'registration',
                'amount' => $registrationAmount,
                'mode' => $paymentMode,
                'status' => 'pending',
                'idempotency_key' => (string) Str::uuid(),
                'cash_status' => $paymentMode === 'cash' ? 'pending_verification' : null,
            ]);

            $member->load(['payments', 'emiSchedule']);

            return $member;
        });
    }
}

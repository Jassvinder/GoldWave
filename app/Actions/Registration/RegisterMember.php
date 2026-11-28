<?php

namespace App\Actions\Registration;

use App\Models\EmiSchedule;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\CashPaymentAwaitingApproval;
use App\Services\BinaryPlacementResolver;
use App\Services\Notifier;
use App\Services\Payments\PaymentModes;
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
     *                                      payment_mode. (EMI plans always start on Future Rate, T-116.)
     */
    public function __invoke(array $data): Member
    {
        $attempt = 0;

        while (true) {
            try {
                $member = $this->attempt($data);

                $this->announceCashPayment($member);

                return $member;
            } catch (QueryException $e) {
                $isUniqueViolation = str_contains($e->getMessage(), 'members_placement_unique');
                if (! $isUniqueViolation || ++$attempt >= self::MAX_PLACEMENT_RETRIES) {
                    throw $e;
                }
                // Concurrent registration took the same placement slot — resolve again and retry.
            }
        }
    }

    /** T-141 — a cash registration waits for the Super Admin's approval, so the Super Admin is told (bell + Notifications page). */
    private function announceCashPayment(Member $member): void
    {
        $payment = $member->payments->firstWhere('type', 'registration');

        if ($payment !== null && PaymentModes::needsApproval($payment->mode)) {
            Notifier::toSuperAdmins(new CashPaymentAwaitingApproval($payment));
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
        $paymentMode = (string) $data['payment_mode'];

        return DB::transaction(function () use ($data, $sponsorCode, $placementSide, $membershipPlanId, $paymentMode) {
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

            // Gender is required by RegisterMemberRequest (T-122); Actions called directly (seeders/tests) may omit it.
            return $this->createId($user, $sponsor, $placement, $plan, $paymentMode, ['gender' => $data['gender'] ?? null]);
        });
    }

    /**
     * One pending ID: the member row, its EMI schedule header (EMI plans) and its registration payment. Shared with
     * `RegisterMemberIds` (T-251), which calls it once per ID of a multi-ID request with `$batchId` set; such a payment
     * carries no proof of its own, the batch does.
     *
     * @param  array{parent_id: int, side: string}  $placement
     * @param  array<string, mixed>  $profile  member columns copied onto the new ID (gender, and for a 2nd ID the KYC)
     */
    public function createId(
        User $user,
        Member $sponsor,
        array $placement,
        MembershipPlan $plan,
        string $paymentMode,
        array $profile = [],
        ?int $batchId = null,
    ): Member {
        return DB::transaction(function () use ($user, $sponsor, $placement, $plan, $paymentMode, $profile, $batchId) {
            $member = Member::create([
                ...$profile,
                'user_id' => $user->id,
                'customer_id' => null,
                'sponsor_id' => $sponsor->id,
                'placement_parent_id' => $placement['parent_id'],
                'placement_side' => $placement['side'],
                'membership_plan_id' => $plan->id,
                'status' => 'draft',
            ]);

            if ($plan->isEmiPlan()) {
                // T-116 (20-09-2026): no rate-booking choice at registration — every EMI plan starts on Future Rate
                // (plain plan amount); the member may book at the Current Rate later (Actions/Emi/BookCurrentRate).
                $rateBooking = ($this->calculateEmiRateBooking)($plan, 'future_rate');

                EmiSchedule::create([
                    'member_id' => $member->id,
                    'membership_plan_id' => $plan->id,
                    'total_installments' => $plan->installment_count,
                    'rate_booking_method' => 'future_rate',
                    'installment_amount' => $rateBooking['installment_amount'],
                    'metal_rate_id' => $rateBooking['metal_rate_id'],
                    'rate_per_gram_at_booking' => $rateBooking['rate_per_gram'],
                    'fixed_weight_grams' => $rateBooking['fixed_weight_grams'],
                    'maintenance_cost' => $rateBooking['maintenance_cost'],
                    'rule_version_id' => $rateBooking['rule_version_id'],
                    'future_commitment_amount' => $rateBooking['future_commitment_amount'],
                    // T-237 — kept so a later plan edit never changes this member (DOMAIN_LOGIC.md §3.3).
                    'plan_installment_amount' => $plan->amount,
                    'plan_fixed_weight_grams' => $plan->fixed_weight_grams,
                ]);

                $registrationAmount = $rateBooking['installment_amount'];
            } else {
                $registrationAmount = $plan->amount;
            }

            $member->update(['status' => 'payment_pending']);

            Payment::create([
                'member_id' => $member->id,
                'payment_batch_id' => $batchId,
                'type' => 'registration',
                'amount' => $registrationAmount,
                'mode' => $paymentMode,
                'status' => 'pending',
                'idempotency_key' => (string) Str::uuid(),
                'cash_status' => PaymentModes::initialApprovalStatus($paymentMode),
            ]);

            $member->load(['payments', 'emiSchedule']);

            return $member;
        });
    }
}

<?php

namespace App\Actions\Registration;

use App\Models\Member;
use App\Models\MetalRate;
use App\Models\Payment;
use App\Models\ProductBenefit;
use App\Services\CustomerIdGenerator;
use Illuminate\Support\Facades\DB;

/**
 * DOMAIN_LOGIC.md §3.1 steps 9-11 / §2.1 points 6-7: activates a member once
 * their registration payment is confirmed — generates the Customer ID, sets
 * the member Active, seeds the initial password (= Customer ID, §2.2), and
 * — for one-time plans only — records the product benefit at the
 * then-current rate. For EMI plans, generates the full `emi_installments`
 * schedule (T-005 — see GenerateEmiInstallments). Idempotent: a member
 * already Active is a no-op, so a retried webhook or a double cash-approval
 * click never re-activates, regenerates a Customer ID, or duplicates the
 * installment schedule.
 *
 * EMI plans (A-D) do NOT get a product benefit here — DOMAIN_LOGIC.md §3.0
 * is explicit that the jewellery is received "at final EMI completion,"
 * which is T-019 territory (EMI due-processor scheduling), not registration
 * activation.
 *
 * Also intentionally does not fire Level Income / Pair Entry creation here —
 * those compensation engines don't exist yet (T-006/T-007 are still
 * Pending). The caller dispatches `PaymentConfirmed` so those listeners can
 * be added later without touching this class (ARCHITECTURE.md's
 * event-driven wiring).
 */
class ActivateMembershipOnPaymentConfirmed
{
    public function __construct(
        private readonly CustomerIdGenerator $customerIdGenerator,
        private readonly GenerateEmiInstallments $generateEmiInstallments,
    ) {}

    public function __invoke(Member $member, ?int $activatedByUserId, Payment $registrationPayment): Member
    {
        return DB::transaction(function () use ($member, $activatedByUserId, $registrationPayment) {
            $member = Member::whereKey($member->id)->lockForUpdate()->firstOrFail();

            if ($member->status === 'active') {
                return $member;
            }

            $customerId = $this->customerIdGenerator->next();

            $member->update([
                'customer_id' => $customerId,
                'status' => 'active',
                'activated_at' => now(),
                'activated_by' => $activatedByUserId,
            ]);

            $member->loadMissing('user', 'membershipPlan');

            // Initial default password = Customer ID itself (deliberate, not a placeholder — DOMAIN_LOGIC.md §2.2).
            $member->user?->update(['password' => $customerId]);

            $plan = $member->membershipPlan;

            if ($plan && ! $plan->isEmiPlan()) {
                $latestRate = MetalRate::where('metal', $plan->product_category)
                    ->whereDate('effective_from', '<=', now()->toDateString())
                    ->orderByDesc('effective_from')
                    ->first();

                ProductBenefit::create([
                    'member_id' => $member->id,
                    'membership_plan_id' => $plan->id,
                    'metal' => $plan->product_category,
                    'metal_rate_id' => $latestRate?->id,
                    'rate_per_gram_at_entry' => $latestRate?->rate_per_gram,
                    'entry_date' => now()->toDateString(),
                ]);
            } elseif ($plan && $plan->isEmiPlan()) {
                ($this->generateEmiInstallments)($member, $registrationPayment);
            }

            return $member->fresh();
        });
    }
}

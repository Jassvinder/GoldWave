<?php

namespace App\Actions\Billing;

use App\Models\Member;
use App\Models\ProductBenefit;
use Illuminate\Validation\ValidationException;

/**
 * T-171 (28-09-2026) — the plan-jewellery entitlement a delivery (store or company) hands over.
 *
 * A one-time plan gets its `product_benefits` row at activation. An EMI plan never had one, so its jewellery could not
 * be delivered at all. The user's rule is that the bill is made **after the last EMI**, at delivery; so once every
 * installment is paid, the entitlement row is created here (on first delivery), carrying the locked booking rate for a
 * Current Rate member. Before the last EMI is paid, delivery is refused.
 */
class ResolveDeliverableBenefit
{
    public function __invoke(Member $member): ProductBenefit
    {
        $undelivered = $member->productBenefits()->whereNull('delivered_at')->first();

        if ($undelivered !== null) {
            return $undelivered;
        }

        $plan = $member->membershipPlan()->first();
        $schedule = $member->emiSchedule()->first();

        if ($plan === null || ! $plan->isEmiPlan() || $schedule === null) {
            throw ValidationException::withMessages(['customer_id' => 'This member has no undelivered plan jewellery entitlement.']);
        }

        if ($member->productBenefits()->exists()) {
            throw ValidationException::withMessages(['customer_id' => "This member's plan jewellery has already been delivered."]);
        }

        $unpaid = $schedule->installments()->where('status', '!=', 'paid')->count();

        if ($unpaid > 0) {
            throw ValidationException::withMessages([
                'customer_id' => "Plan jewellery is delivered after the last EMI — {$unpaid} EMI(s) still to pay.",
            ]);
        }

        $isCurrentRate = $schedule->rate_booking_method === 'current_rate';

        return ProductBenefit::create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'metal' => $plan->product_category,
            'metal_rate_id' => $isCurrentRate ? $schedule->metal_rate_id : null,
            'rate_per_gram_at_entry' => $isCurrentRate ? $schedule->rate_per_gram_at_booking : null,
            'entry_date' => now()->toDateString(),
        ]);
    }
}

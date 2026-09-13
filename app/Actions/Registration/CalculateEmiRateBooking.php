<?php

namespace App\Actions\Registration;

use App\Models\MembershipPlan;
use App\Models\MetalRate;
use App\Services\RuleVersionService;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §3.0 — the Current-Rate-vs-Future-Rate Booking formulas for
 * EMI plans (A-D). Centralized here (not inlined in RegisterMember) because
 * T-005's full EMI-schedule generation reuses the exact same formula — this
 * is the single source of truth for "what does this plan's EMI amount come
 * out to under this booking method," matching ARCHITECTURE.md's rule that a
 * calculation must never be reimplemented in more than one place.
 */
class CalculateEmiRateBooking
{
    public function __construct(private readonly RuleVersionService $ruleVersionService) {}

    /**
     * @param  string  $method  Expected to be 'current_rate' or 'future_rate' (validated by
     *                          RegisterMemberRequest); not narrowed to a literal union here since
     *                          this Action re-validates it itself as a defensive boundary check.
     * @return array{
     *     installment_amount: float,
     *     metal_rate_id: int|null,
     *     rate_per_gram: float|null,
     *     fixed_weight_grams: float|null,
     *     maintenance_cost: float|null,
     *     rule_version_id: int|null,
     *     future_commitment_amount: float|null,
     * }
     */
    public function __invoke(MembershipPlan $plan, string $method): array
    {
        if (! $plan->isEmiPlan()) {
            throw ValidationException::withMessages([
                'rate_booking_method' => 'Rate booking only applies to EMI plans.',
            ]);
        }

        if ($method === 'future_rate') {
            return $this->futureRate($plan);
        }

        if ($method === 'current_rate') {
            return $this->currentRate($plan);
        }

        throw ValidationException::withMessages([
            'rate_booking_method' => 'Select Current Rate Booking or Future Rate Booking.',
        ]);
    }

    /**
     * @return array{
     *     installment_amount: float,
     *     metal_rate_id: int|null,
     *     rate_per_gram: float|null,
     *     fixed_weight_grams: float|null,
     *     maintenance_cost: float|null,
     *     rule_version_id: int|null,
     *     future_commitment_amount: float|null,
     * }
     */
    private function futureRate(MembershipPlan $plan): array
    {
        return [
            'installment_amount' => (float) $plan->amount,
            'metal_rate_id' => null,
            'rate_per_gram' => null,
            'fixed_weight_grams' => null,
            'maintenance_cost' => null,
            'rule_version_id' => null,
            'future_commitment_amount' => round((float) $plan->amount * $plan->installment_count, 2),
        ];
    }

    /**
     * @return array{
     *     installment_amount: float,
     *     metal_rate_id: int|null,
     *     rate_per_gram: float|null,
     *     fixed_weight_grams: float|null,
     *     maintenance_cost: float|null,
     *     rule_version_id: int|null,
     *     future_commitment_amount: float|null,
     * }
     */
    private function currentRate(MembershipPlan $plan): array
    {
        if (! $plan->fixed_weight_grams) {
            throw ValidationException::withMessages([
                'rate_booking_method' => 'This plan has no fixed jewellery weight configured for Current Rate Booking.',
            ]);
        }

        $latestRate = MetalRate::where('metal', $plan->product_category)
            ->whereDate('effective_from', '<=', now()->toDateString())
            ->orderByDesc('effective_from')
            ->first();

        if (! $latestRate) {
            throw ValidationException::withMessages([
                'rate_booking_method' => 'No metal rate has been configured yet — Super Admin must set a rate before Current Rate Booking can be used.',
            ]);
        }

        $maintenancePercent = (float) $this->ruleVersionService->value('emi_current_rate_maintenance_cost_percent', 1.0);

        $totalValue = round((float) $latestRate->rate_per_gram * (float) $plan->fixed_weight_grams, 2);
        $maintenanceCost = round($totalValue * $maintenancePercent / 100, 2);
        $installmentAmount = round($totalValue / $plan->installment_count + $maintenanceCost, 2);

        return [
            'installment_amount' => $installmentAmount,
            'metal_rate_id' => $latestRate->id,
            'rate_per_gram' => (float) $latestRate->rate_per_gram,
            'fixed_weight_grams' => (float) $plan->fixed_weight_grams,
            'maintenance_cost' => $maintenanceCost,
            'rule_version_id' => $this->ruleVersionService->activeVersion()?->id,
            'future_commitment_amount' => null,
        ];
    }
}

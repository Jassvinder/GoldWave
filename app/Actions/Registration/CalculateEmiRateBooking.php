<?php

namespace App\Actions\Registration;

use App\Models\MembershipPlan;
use App\Models\MetalRate;
use App\Services\RuleVersionService;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §3.0 — the Current-Rate-vs-Future-Rate Booking formulas for
 * EMI plans (A-D). Centralized here because it is the single source of truth
 * for "what does this plan's EMI amount come out to under this booking
 * method", matching ARCHITECTURE.md's rule that a calculation must never be
 * reimplemented in more than one place.
 *
 * Since T-116 (20-09-2026) registration only ever asks for `future_rate`;
 * `current_rate` is evaluated when an existing member books at the Current
 * Rate after paying some EMIs (`Actions/Emi/QuoteCurrentRateBooking`), so it
 * takes the EMIs already paid into account: paid EMIs are credited against the
 * metal value and carry no maintenance. With nothing paid it reduces to the
 * original whole-schedule formula.
 *
 * @phpstan-type RateBooking array{
 *     installment_amount: float,
 *     metal_rate_id: int|null,
 *     rate_per_gram: float|null,
 *     fixed_weight_grams: float|null,
 *     maintenance_cost: float|null,
 *     rule_version_id: int|null,
 *     future_commitment_amount: float|null,
 *     metal_value: float|null,
 *     making_charge_percent: float|null,
 *     making_charges: float|null,
 *     total_value: float|null,
 *     remaining_value: float|null,
 *     pending_installments: int|null,
 *     installment_amounts: non-empty-list<float>|null,
 *     total_maintenance: float|null,
 * }
 */
class CalculateEmiRateBooking
{
    public function __construct(private readonly RuleVersionService $ruleVersionService) {}

    /**
     * @param  string  $method  Expected to be 'current_rate' or 'future_rate'; not narrowed to a literal union
     *                          here since this Action re-validates it itself as a defensive boundary check.
     * @param  int  $paidInstallments  EMIs already paid (Current Rate only).
     * @param  float  $paidAmount  Sum of the EMIs already paid (Current Rate only).
     * @return RateBooking
     */
    public function __invoke(MembershipPlan $plan, string $method, int $paidInstallments = 0, float $paidAmount = 0.0): array
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
            return $this->currentRate($plan, $paidInstallments, $paidAmount);
        }

        throw ValidationException::withMessages([
            'rate_booking_method' => 'Select Current Rate Booking or Future Rate Booking.',
        ]);
    }

    /** @return RateBooking */
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
            'metal_value' => null,
            'making_charge_percent' => null,
            'making_charges' => null,
            'total_value' => null,
            'remaining_value' => null,
            'pending_installments' => null,
            'installment_amounts' => null,
            'total_maintenance' => null,
        ];
    }

    /** @return RateBooking */
    private function currentRate(MembershipPlan $plan, int $paidInstallments, float $paidAmount): array
    {
        if (! $plan->fixed_weight_grams) {
            throw ValidationException::withMessages([
                'rate_booking_method' => 'This plan has no fixed jewellery weight configured for Current Rate Booking.',
            ]);
        }

        $pendingInstallments = (int) $plan->installment_count - $paidInstallments;

        if ($pendingInstallments < 1) {
            throw ValidationException::withMessages([
                'rate_booking_method' => 'Every EMI of this plan is already paid — there is nothing left to book.',
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

        // T-165 (28-09-2026) — the booking's total value is metal value + making charges (the making % recorded
        // with the rate), the same way a bill is priced; hallmark and GST are not part of the booking.
        $metalValue = round((float) $latestRate->rate_per_gram * (float) $plan->fixed_weight_grams, 2);
        $makingPercent = (float) $latestRate->making_charge_percent;
        $makingCharges = round($metalValue * $makingPercent / 100, 2);
        $totalValue = round($metalValue + $makingCharges, 2);
        $remainingValue = round($totalValue - $paidAmount, 2);

        if ($remainingValue <= 0) {
            throw ValidationException::withMessages([
                'rate_booking_method' => 'The EMIs already paid cover the whole jewellery value at the current rate, so Current Rate Booking is not available.',
            ]);
        }

        $installmentAmounts = $this->decliningInstallments($remainingValue, $pendingInstallments, $maintenancePercent);

        return [
            // The first EMI after booking; each later one is smaller (see `installment_amounts`).
            'installment_amount' => $installmentAmounts[0],
            'metal_rate_id' => $latestRate->id,
            'rate_per_gram' => (float) $latestRate->rate_per_gram,
            'fixed_weight_grams' => (float) $plan->fixed_weight_grams,
            // The first month's maintenance (1% of the full remaining value).
            'maintenance_cost' => round($remainingValue * $maintenancePercent / 100, 2),
            'rule_version_id' => $this->ruleVersionService->activeVersion()?->id,
            'future_commitment_amount' => null,
            'metal_value' => $metalValue,
            'making_charge_percent' => $makingPercent,
            'making_charges' => $makingCharges,
            'total_value' => $totalValue,
            'remaining_value' => $remainingValue,
            'pending_installments' => $pendingInstallments,
            'installment_amounts' => $installmentAmounts,
            'total_maintenance' => round(array_sum($installmentAmounts) - $remainingValue, 2),
        ];
    }

    /**
     * T-167 (28-09-2026, user decision — DOMAIN_LOGIC.md §21 / TEST.md scenario 21): the principal is the same
     * every month, and each month's maintenance is the percentage of the value still remaining at that
     * installment, so every EMI is a little smaller than the one before. Any principal rounding remainder goes
     * into the last EMI, so the principals always add up to exactly the remaining value.
     *
     * @return non-empty-list<float>
     */
    private function decliningInstallments(float $remainingValue, int $pendingInstallments, float $maintenancePercent): array
    {
        $principal = round($remainingValue / $pendingInstallments, 2);

        $amountFor = function (int $month) use ($remainingValue, $pendingInstallments, $maintenancePercent, $principal): float {
            $stillRemaining = round($remainingValue - $principal * ($month - 1), 2);
            $thisPrincipal = $month === $pendingInstallments ? $stillRemaining : $principal;

            return round($thisPrincipal + round($stillRemaining * $maintenancePercent / 100, 2), 2);
        };

        // The caller guarantees at least one pending EMI.
        $amounts = [$amountFor(1)];

        for ($month = 2; $month <= $pendingInstallments; $month++) {
            $amounts[] = $amountFor($month);
        }

        return $amounts;
    }
}

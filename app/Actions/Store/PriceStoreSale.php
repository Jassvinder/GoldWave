<?php

namespace App\Actions\Store;

use App\Models\MetalRate;
use App\Services\RuleVersionService;
use Illuminate\Validation\ValidationException;

/**
 * T-169 (28-09-2026, user decision — DOMAIN_LOGIC.md §21 "28-09-2026
 * feedback batch", TEST.md scenario 31): the one place a store sale's price
 * is worked out. The store never types the amount:
 *
 *   metal value = weight per piece × quantity × rate per gram
 *   making      = metal value × making % (from the same rate row)
 *   subtotal    = metal value + making (+ hallmark, T-171)
 *   GST         = subtotal × Super Admin's `store_gst_percent`
 *   total       = subtotal + GST
 *
 * The rate is the latest one entered on or before today, so a day with no new
 * entry keeps the previous rate. Income (Store Profit, Purchase/Repurchase)
 * is calculated on the metal value only.
 *
 * @phpstan-type SalePrice array{
 *     metal_rate_id: int,
 *     rate_per_gram: float,
 *     metal_value: float,
 *     making_charge_percent: float,
 *     making_charges: float,
 *     hallmark_charges: float,
 *     subtotal: float,
 *     gst_percent: float,
 *     gst_amount: float,
 *     total: float,
 * }
 */
class PriceStoreSale
{
    public function __construct(private readonly RuleVersionService $rules) {}

    /**
     * Priced at today's rate. `$lockedRatePerGram` / `$lockedRateId` replace only the rate — used for a plan
     * jewellery delivery to a Current Rate member, whose rate was locked at booking (DOMAIN_LOGIC.md §21,
     * 28-09-2026). Making % always comes from today's rate row (the delivery-day setting).
     *
     * @return SalePrice
     */
    public function __invoke(string $metal, float $weightPerPiece, int $quantity, ?float $lockedRatePerGram = null, ?int $lockedRateId = null, float $hallmarkCharges = 0.0): array
    {
        $rate = self::currentRate($metal);

        if ($rate === null) {
            throw ValidationException::withMessages([
                'metal' => "No {$metal} rate has been set yet — Super Admin must enter one first.",
            ]);
        }

        $ratePerGram = $lockedRatePerGram ?? (float) $rate->rate_per_gram;
        $metalValue = round($weightPerPiece * $quantity * $ratePerGram, 2);
        $makingPercent = (float) $rate->making_charge_percent;
        $makingCharges = round($metalValue * $makingPercent / 100, 2);
        // T-171 — hallmark charges are added before GST.
        $subtotal = round($metalValue + $makingCharges + $hallmarkCharges, 2);
        $gstPercent = (float) $this->rules->value('store_gst_percent', 0);
        $gstAmount = round($subtotal * $gstPercent / 100, 2);

        return [
            'metal_rate_id' => $lockedRatePerGram !== null ? ($lockedRateId ?? $rate->id) : $rate->id,
            'rate_per_gram' => $ratePerGram,
            'metal_value' => $metalValue,
            'making_charge_percent' => $makingPercent,
            'making_charges' => $makingCharges,
            'hallmark_charges' => round($hallmarkCharges, 2),
            'subtotal' => $subtotal,
            'gst_percent' => $gstPercent,
            'gst_amount' => $gstAmount,
            'total' => round($subtotal + $gstAmount, 2),
        ];
    }

    /** The latest rate row for this metal effective on or before today, if any. */
    public static function currentRate(string $metal): ?MetalRate
    {
        return MetalRate::where('metal', $metal)
            ->whereDate('effective_from', '<=', now()->toDateString())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }
}

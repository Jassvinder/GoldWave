<?php

namespace App\Actions\Settings;

use App\Models\MetalRate;
use App\Models\User;

/**
 * INSTRUCTIONS.md S07 (Gold & Silver Rate Settings), DOMAIN_LOGIC.md §5 —
 * records a new effective-dated rate row. `metal_rates` keeps every rate
 * ever set (no update-in-place), matching this project's "never silently
 * alter the calculation basis of already-finalized historical transactions"
 * principle — every EMI/store-sale/buyback Action already resolves "the
 * current rate" as the latest row on/before today, never a live-mutated one.
 *
 * T-165 (28-09-2026) — Super Admin enters the rate **per 10 gm** (1 tola =
 * 10 gm in this project) plus a making-charges %; the rate is stored per gram
 * (÷ 10). The request allows at most one decimal per 10 gm, so the per-gram
 * figure is always exact at 2 decimals.
 */
class SetMetalRate
{
    public function __invoke(string $metal, float $ratePer10Grams, float $makingChargePercent, string $effectiveFrom, User $operator): MetalRate
    {
        return MetalRate::create([
            'metal' => $metal,
            'rate_per_gram' => round($ratePer10Grams / 10, 2),
            'making_charge_percent' => $makingChargePercent,
            'effective_from' => $effectiveFrom,
            'created_by' => $operator->id,
        ]);
    }
}

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
 */
class SetMetalRate
{
    public function __invoke(string $metal, float $ratePerGram, string $effectiveFrom, User $operator): MetalRate
    {
        return MetalRate::create([
            'metal' => $metal,
            'rate_per_gram' => $ratePerGram,
            'effective_from' => $effectiveFrom,
            'created_by' => $operator->id,
        ]);
    }
}

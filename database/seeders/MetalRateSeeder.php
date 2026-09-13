<?php

namespace Database\Seeders;

use App\Models\MetalRate;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seed rates so Current Rate Booking (DOMAIN_LOGIC.md §3.0) is usable out of
 * the box. These are placeholder starting values a Super Admin can change
 * any time on the Rate Settings page (S07) — not a business default. The
 * silver figure matches DOMAIN_LOGIC.md §3.0's own worked example
 * (₹3,500/tola = ₹350/gram) so `Docs/TEST.md` scenario 10's numbers reproduce
 * exactly against a freshly-seeded database.
 */
class MetalRateSeeder extends Seeder
{
    public function run(): void
    {
        $operator = User::where('role', 'super_admin')->first();

        if (! $operator) {
            return; // no seeded operator available (e.g. production) — Super Admin sets the first rate via S07.
        }

        if (! MetalRate::where('metal', 'silver')->exists()) {
            MetalRate::create([
                'metal' => 'silver',
                'rate_per_gram' => 350.00,
                'effective_from' => now()->toDateString(),
                'created_by' => $operator->id,
            ]);
        }

        if (! MetalRate::where('metal', 'gold')->exists()) {
            MetalRate::create([
                'metal' => 'gold',
                'rate_per_gram' => 6000.00,
                'effective_from' => now()->toDateString(),
                'created_by' => $operator->id,
            ]);
        }
    }
}

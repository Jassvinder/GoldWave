<?php

namespace Database\Seeders;

use App\Models\MembershipPlan;
use Illuminate\Database\Seeder;

/**
 * DOMAIN_LOGIC.md §3 — the finalized A-F plan table (client spec v2.0,
 * resolved 12-09-2026). `fixed_weight_grams` is null for E/F (one-time
 * plans: member chooses jewellery at the rate applicable on the payment
 * confirmation date, no fixed weight is booked).
 */
class MembershipPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['code' => 'A', 'name' => 'Plan A — ₹1,000 × 20 months', 'amount' => 1000, 'installment_count' => 20, 'product_category' => 'silver', 'fixed_weight_grams' => 100],
            ['code' => 'B', 'name' => 'Plan B — ₹3,000 × 10 months', 'amount' => 3000, 'installment_count' => 10, 'product_category' => 'silver', 'fixed_weight_grams' => 100],
            ['code' => 'C', 'name' => 'Plan C — ₹5,000 × 10 months', 'amount' => 5000, 'installment_count' => 10, 'product_category' => 'gold', 'fixed_weight_grams' => 5],
            ['code' => 'D', 'name' => 'Plan D — ₹10,000 × 10 months', 'amount' => 10000, 'installment_count' => 10, 'product_category' => 'gold', 'fixed_weight_grams' => 10],
            ['code' => 'E', 'name' => 'Plan E — ₹20,000 one-time', 'amount' => 20000, 'installment_count' => null, 'product_category' => 'silver', 'fixed_weight_grams' => null],
            ['code' => 'F', 'name' => 'Plan F — ₹50,000 one-time', 'amount' => 50000, 'installment_count' => null, 'product_category' => 'gold', 'fixed_weight_grams' => null],
        ];

        foreach ($plans as $plan) {
            MembershipPlan::updateOrCreate(['code' => $plan['code']], [...$plan, 'is_active' => true]);
        }
    }
}

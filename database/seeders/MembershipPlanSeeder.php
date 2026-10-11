<?php

namespace Database\Seeders;

use App\Models\MembershipPlan;
use Illuminate\Database\Seeder;

/**
 * DOMAIN_LOGIC.md §3 — the finalized A-F plan table (client spec v2.0,
 * resolved 12-09-2026). `fixed_weight_grams` is null for E-H (one-time
 * plans: member chooses jewellery at the rate applicable on the payment
 * confirmation date, no fixed weight is booked).
 *
 * T-154 (25-09-2026, user decision) — marketing names, never the internal
 * A-F code, are shown anywhere in the UI; the code stays as the invariant
 * plan identifier everywhere else in the codebase (routes, compensation
 * logic, `RuleVersionService::metalValue()`, etc.) — only this display
 * `name` string changed. E/F ("Direct") are the same total value as A/C
 * respectively, just paid upfront instead of by EMI — named accordingly,
 * not as a higher tier ("Crown" was rejected for implying a premium tier
 * above D/"Elite" that the actual ₹ value doesn't support).
 *
 * T-217 (09-10-2026, user request) — every EMI plan now has a one-time
 * twin of the same total: E = A (₹20,000), G = B (₹30,000), F = C
 * (₹50,000), H = D (₹1,00,000). Each Direct plan is named after its EMI
 * twin.
 */
class MembershipPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['code' => 'A', 'name' => 'Silver Start', 'amount' => 1000, 'installment_count' => 20, 'product_category' => 'silver', 'fixed_weight_grams' => 100],
            ['code' => 'B', 'name' => 'Silver Prime', 'amount' => 3000, 'installment_count' => 10, 'product_category' => 'silver', 'fixed_weight_grams' => 100],
            ['code' => 'C', 'name' => 'Gold Rise', 'amount' => 5000, 'installment_count' => 10, 'product_category' => 'gold', 'fixed_weight_grams' => 5],
            ['code' => 'D', 'name' => 'Gold Elite', 'amount' => 10000, 'installment_count' => 10, 'product_category' => 'gold', 'fixed_weight_grams' => 10],
            ['code' => 'E', 'name' => 'Silver Start Direct', 'amount' => 20000, 'installment_count' => null, 'product_category' => 'silver', 'fixed_weight_grams' => null],
            ['code' => 'F', 'name' => 'Gold Rise Direct', 'amount' => 50000, 'installment_count' => null, 'product_category' => 'gold', 'fixed_weight_grams' => null],
            ['code' => 'G', 'name' => 'Silver Prime Direct', 'amount' => 30000, 'installment_count' => null, 'product_category' => 'silver', 'fixed_weight_grams' => null],
            ['code' => 'H', 'name' => 'Gold Elite Direct', 'amount' => 100000, 'installment_count' => null, 'product_category' => 'gold', 'fixed_weight_grams' => null],
        ];

        foreach ($plans as $plan) {
            MembershipPlan::updateOrCreate(['code' => $plan['code']], [...$plan, 'is_active' => true]);
        }
    }
}

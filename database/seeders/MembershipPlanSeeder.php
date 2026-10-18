<?php

namespace Database\Seeders;

use App\Models\MembershipPlan;
use Illuminate\Database\Seeder;

/**
 * DOMAIN_LOGIC.md §3 — the finalized A-F plan table (client spec v2.0,
 * resolved 12-09-2026). `fixed_weight_grams` is null for E/F (one-time
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
            // 09-10-2026 (user decision): one-time versions of B and D. They were added on the dev DB only and so were
            // missing from fresh installs, including the first live deploy (found and moved here 14-10-2026).
            ['code' => 'G', 'name' => 'Silver Prime Direct', 'amount' => 30000, 'installment_count' => null, 'product_category' => 'silver', 'fixed_weight_grams' => null],
            ['code' => 'H', 'name' => 'Gold Elite Direct', 'amount' => 100000, 'installment_count' => null, 'product_category' => 'gold', 'fixed_weight_grams' => null],
        ];

        // T-237 (DOMAIN_LOGIC.md §3.3) — only creates a missing plan. Super Admin owns the live values on the Membership
        // Plans page, so re-running the seeder must never overwrite an edited name, price or On/Off state.
        foreach ($plans as $plan) {
            MembershipPlan::firstOrCreate(['code' => $plan['code']], [...$plan, 'is_active' => true]);
        }
    }
}

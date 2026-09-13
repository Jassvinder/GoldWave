<?php

namespace Database\Seeders;

use App\Models\RuleVersion;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * DOMAIN_LOGIC.md §0/§7.4/§22 — nothing configurable is hard-coded; T-003
 * only needs `emi_current_rate_maintenance_cost_percent` (the "1%" in §3.0's
 * Current Rate Booking formula). Later tasks (T-006/T-007/T-009/T-011)
 * append more keys to this same active version rather than creating a new
 * one for every feature — a new version is only published when a value
 * actually changes after go-live (RuleVersionService).
 */
class RuleVersionSeeder extends Seeder
{
    public function run(): void
    {
        if (RuleVersion::where('is_active', true)->exists()) {
            return;
        }

        $operator = User::where('role', 'super_admin')->first();

        $version = RuleVersion::create([
            'version_no' => 1,
            'effective_from' => now()->toDateString(),
            'is_active' => true,
            'published_by' => $operator?->id,
            'published_at' => now(),
            'notes' => 'Initial rule version — seeded for T-003 (EMI Current Rate Booking maintenance cost).',
        ]);

        $version->values()->create([
            'key' => 'emi_current_rate_maintenance_cost_percent',
            'value' => 1.0,
        ]);

        // DOMAIN_LOGIC.md §6 — Level Income rates per Sponsor/Direct level
        // (1-12). Stored as a flat level=>percent map (not "4-8"/"9-12"
        // range keys) so CalculateLevelIncome (T-006) can look up each level
        // directly without parsing a range string.
        $version->values()->create([
            'key' => 'level_income_rates',
            'value' => [
                '1' => 5, '2' => 2, '3' => 2,
                '4' => 1, '5' => 1, '6' => 1, '7' => 1, '8' => 1,
                '9' => 0.5, '10' => 0.5, '11' => 0.5, '12' => 0.5,
            ],
        ]);

        // DOMAIN_LOGIC.md §7.3 — minimum completed EMIs before an EMI-plan
        // joining counts as "fully eligible" for Pair/Reward. One-time plans
        // (E/F, installment_count null) have no entry here — they're always
        // eligible on their single registration payment.
        $version->values()->create([
            'key' => 'pair_qualification_emis',
            'value' => ['A' => 6, 'B' => 2, 'C' => 2, 'D' => 1],
        ]);

        // DOMAIN_LOGIC.md §7.1/§7.4 — "pair value" (₹ per eligible entry) is
        // its own configurable rate; each milestone's reward is derived as
        // (left + right) * pair_value_per_entry, not stored as a separate
        // fixed number per milestone (verified against every row of the §7.1
        // table — e.g. milestone 1: (5+5)*50 = ₹500).
        $version->values()->create([
            'key' => 'pair_value_per_entry',
            'value' => 50,
        ]);

        // DOMAIN_LOGIC.md §7.1 — the 15 milestones' thresholds and per-milestone
        // minimum Direct Members gate (default 2 for every milestone, §7.3).
        // Reward amounts are intentionally omitted — derived from `left`/`right`
        // × pair_value_per_entry above.
        $version->values()->create([
            'key' => 'pair_milestones',
            'value' => [
                ['milestone_no' => 1, 'min_directs' => 2, 'left' => 5, 'right' => 5],
                ['milestone_no' => 2, 'min_directs' => 2, 'left' => 50, 'right' => 50],
                ['milestone_no' => 3, 'min_directs' => 2, 'left' => 250, 'right' => 250],
                ['milestone_no' => 4, 'min_directs' => 2, 'left' => 500, 'right' => 500],
                ['milestone_no' => 5, 'min_directs' => 2, 'left' => 1000, 'right' => 1000],
                ['milestone_no' => 6, 'min_directs' => 2, 'left' => 2000, 'right' => 2000],
                ['milestone_no' => 7, 'min_directs' => 2, 'left' => 5000, 'right' => 5000],
                ['milestone_no' => 8, 'min_directs' => 2, 'left' => 10000, 'right' => 10000],
                ['milestone_no' => 9, 'min_directs' => 2, 'left' => 20000, 'right' => 20000],
                ['milestone_no' => 10, 'min_directs' => 2, 'left' => 40000, 'right' => 40000],
                ['milestone_no' => 11, 'min_directs' => 2, 'left' => 80000, 'right' => 80000],
                ['milestone_no' => 12, 'min_directs' => 2, 'left' => 160000, 'right' => 160000],
                ['milestone_no' => 13, 'min_directs' => 2, 'left' => 320000, 'right' => 320000],
                ['milestone_no' => 14, 'min_directs' => 2, 'left' => 640000, 'right' => 640000],
                ['milestone_no' => 15, 'min_directs' => 2, 'left' => 1280000, 'right' => 1280000],
            ],
        ]);
    }
}

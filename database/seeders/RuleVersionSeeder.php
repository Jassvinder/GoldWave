<?php

namespace Database\Seeders;

use App\Models\RuleVersion;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * DOMAIN_LOGIC.md §0/§7.4/§22 — nothing configurable is hard-coded; T-003
 * only needs `emi_current_rate_maintenance_cost_percent` (the "1%" in §3.0's
 * Current Rate Booking formula). Later tasks (T-006/T-007/T-009/T-011/T-014)
 * append more keys to this same active version rather than creating a new
 * one for every feature — a new version is only published when a value
 * actually changes after go-live (RuleVersionService).
 *
 * **Discovered during T-015's manual browser QA, fixed here (not a business-
 * rule change):** every key below is seeded via `firstOrCreate` keyed on
 * `(rule_version_id, key)`, not a single blanket "does an active version
 * already exist" guard. The original guard made this seeder's own stated
 * design intent false in practice — once *any* version existed (from T-003's
 * very first `db:seed` run), `run()` returned immediately on every later
 * `php artisan db:seed` call, so every key T-006 through T-019 added here
 * afterward was silently never written to a long-lived dev database that
 * was seeded once early on and never fully reset. The real dev DB had only
 * `emi_current_rate_maintenance_cost_percent` set — booster/pair/draw/store/
 * payout thresholds all silently fell back to `RuleVersionService`'s
 * `$default` parameter (typically an inert `[]`/`0`) at runtime, not the
 * values this file actually specifies. Per-key `firstOrCreate` means running
 * `php artisan db:seed --class=RuleVersionSeeder` again on any existing
 * database — dev or otherwise — safely backfills only what's missing,
 * without duplicating already-seeded keys or creating a second version.
 */
class RuleVersionSeeder extends Seeder
{
    public function run(): void
    {
        $operator = User::where('role', 'super_admin')->first();

        $version = RuleVersion::firstOrCreate(
            ['is_active' => true],
            [
                'version_no' => 1,
                'effective_from' => now()->toDateString(),
                'published_by' => $operator?->id,
                'published_at' => now(),
                'notes' => 'Initial rule version — seeded for T-003 (EMI Current Rate Booking maintenance cost).',
            ],
        );

        $this->seedValue($version, 'emi_current_rate_maintenance_cost_percent', 1.0);

        // DOMAIN_LOGIC.md §6 — Level Income rates per Sponsor/Direct level
        // (1-12). Stored as a flat level=>percent map (not "4-8"/"9-12"
        // range keys) so CalculateLevelIncome (T-006) can look up each level
        // directly without parsing a range string.
        $this->seedValue($version, 'level_income_rates', [
            '1' => 5, '2' => 2, '3' => 2,
            '4' => 1, '5' => 1, '6' => 1, '7' => 1, '8' => 1,
            '9' => 0.5, '10' => 0.5, '11' => 0.5, '12' => 0.5,
        ]);

        // DOMAIN_LOGIC.md §7.3 — minimum completed EMIs before an EMI-plan
        // joining counts as "fully eligible" for Pair/Reward. One-time plans
        // (E/F, installment_count null) have no entry here — they're always
        // eligible on their single registration payment.
        $this->seedValue($version, 'pair_qualification_emis', ['A' => 6, 'B' => 2, 'C' => 2, 'D' => 1]);

        // DOMAIN_LOGIC.md §7.1/§7.4 — "pair value" (₹ per eligible entry) is
        // its own configurable rate; each milestone's reward is derived as
        // (left + right) * pair_value_per_entry, not stored as a separate
        // fixed number per milestone (verified against every row of the §7.1
        // table — e.g. milestone 1: (5+5)*50 = ₹500).
        $this->seedValue($version, 'pair_value_per_entry', 50);

        // DOMAIN_LOGIC.md §7.1 — the 15 milestones' thresholds and per-milestone
        // minimum Direct Members gate (default 2 for every milestone, §7.3).
        // Reward amounts are intentionally omitted — derived from `left`/`right`
        // × pair_value_per_entry above.
        $this->seedValue($version, 'pair_milestones', [
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
        ]);

        // DOMAIN_LOGIC.md §11.1/§11.2 — Payout minimum request amount, TDS
        // rate, and processing fee (fee mechanism is a flagged open item,
        // §21 T-009 pre-coding pass — implemented as a percentage matching
        // TDS; default 0 keeps it inert until the client confirms otherwise).
        $this->seedValue($version, 'payout_min_amount', 500);
        $this->seedValue($version, 'payout_tds_percent', 0);
        $this->seedValue($version, 'payout_processing_fee_percent', 0);

        // DOMAIN_LOGIC.md §8.1 — group size Super Admin can set to 200, 300,
        // 500, or another allowed number; §8.7 — Draw-eligibility completed-EMI
        // thresholds per plan, deliberately a SEPARATE key from
        // pair_qualification_emis even where the numbers happen to match.
        $this->seedValue($version, 'draw_group_size', 200);
        $this->seedValue($version, 'draw_eligibility_emis', ['A' => 6, 'B' => 2, 'C' => 2, 'D' => 1]);

        // DOMAIN_LOGIC.md §9 — Income Booster levels. Left/Right split
        // thresholds are enforced independently, not just their sum
        // (DOMAIN_LOGIC.md §21 T-011 pre-coding pass, user-confirmed).
        $this->seedValue($version, 'booster_levels', [
            ['level_no' => 1, 'min_directs' => 10, 'team_split_left' => 250, 'team_split_right' => 250, 'monthly_benefit' => 5000, 'duration_months' => 6],
            ['level_no' => 2, 'min_directs' => 20, 'team_split_left' => 750, 'team_split_right' => 750, 'monthly_benefit' => 20000, 'duration_months' => 6],
            ['level_no' => 3, 'min_directs' => 30, 'team_split_left' => 1500, 'team_split_right' => 1500, 'monthly_benefit' => 60000, 'duration_months' => 6],
        ]);

        // DOMAIN_LOGIC.md §14.1 — Daily Dynamic Company Direct Entries
        // settings. Disabled/zero by default until Super Admin configures a
        // real daily count, matching this project's "inert until
        // configured" pattern for every other Super-Admin-owned setting.
        $this->seedValue($version, 'dummy_entry_enabled', false);
        $this->seedValue($version, 'dummy_entry_daily_count', 0);

        // DOMAIN_LOGIC.md §15 — Purchase/Repurchase Upline Income: self 2%,
        // direct Sponsor (Level 1) 1%, Levels 2-6 0.5% each, Levels 7-12
        // 0.25% each. Level 1 is deliberately 1%, not the 0.5% a plain
        // "Level 2" rate might imply — the direct Sponsor's rate is its own
        // distinct tier (§15's duplicate-beneficiary rule).
        $this->seedValue($version, 'purchase_repurchase_income_rates', [
            'self' => 2,
            '1' => 1,
            '2' => 0.5, '3' => 0.5, '4' => 0.5, '5' => 0.5, '6' => 0.5,
            '7' => 0.25, '8' => 0.25, '9' => 0.25, '10' => 0.25, '11' => 0.25, '12' => 0.25,
        ]);

        // DOMAIN_LOGIC.md §16.4 — Store Profit Distribution: Owner 2%,
        // Sponsor/Direct L1 0.5%, L2 0.25%, L3 0.25%, on the full
        // distributable sale amount (already net of costs).
        $this->seedValue($version, 'store_profit_distribution_rates', [
            'store_owner' => 2,
            'sponsor_level_1' => 0.5,
            'sponsor_level_2' => 0.25,
            'sponsor_level_3' => 0.25,
        ]);

        // DOMAIN_LOGIC.md §16.7 — Item Buyback percentage of the item's
        // current-market-rate metal value. Client-stated default 60%,
        // explicitly expected to be tuned post-launch (§21 "Still open").
        $this->seedValue($version, 'item_buyback_percent', 60);

        // DOMAIN_LOGIC.md §16.2 — Store sale GST/tax percentage. No source
        // document ever states the actual statutory rate to seed; default 0
        // (inert) until Super Admin confirms the real value, matching this
        // project's "default 0 until confirmed" pattern (§21 "Still open").
        $this->seedValue($version, 'store_gst_percent', 0);
    }

    private function seedValue(RuleVersion $version, string $key, mixed $value): void
    {
        $version->values()->firstOrCreate(['key' => $key], ['value' => $value]);
    }
}

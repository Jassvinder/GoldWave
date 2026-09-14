<?php

use App\Models\BoosterPayoutSchedule;
use App\Models\BoosterQualification;
use App\Models\Member;
use App\Models\RuleVersion;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use Illuminate\Support\Facades\Process;

/**
 * T-020 (DOMAIN_LOGIC.md §21) — real, separate-OS-process concurrency test
 * against the actual dev Postgres database. Mirrors
 * tests/Concurrency/StoreConcurrencyTest.php's pattern. Never uses
 * RefreshDatabase — creates and cleans up its own throwaway fixtures.
 *
 * Run explicitly: php artisan test --configuration=phpunit.concurrency.xml
 */
test('simultaneous scheduler ticks never pay the same booster schedule twice', function () {
    $ruleVersionId = RuleVersion::where('is_active', true)->value('id');
    $user = User::factory()->create(['role' => 'member']);

    $member = Member::create([
        'user_id' => $user->id,
        'customer_id' => 'CONC-BOOST1',
        'status' => 'active',
        'activated_at' => now(),
    ]);

    $qualification = BoosterQualification::create([
        'member_id' => $member->id,
        'level_no' => 1,
        'qualified_at' => now(),
        'rule_version_id' => $ruleVersionId,
    ]);

    $schedule = BoosterPayoutSchedule::create([
        'booster_qualification_id' => $qualification->id,
        'month_no' => 1,
        'scheduled_date' => now()->subDay()->toDateString(),
        'amount' => 5000,
        'status' => 'pending',
    ]);

    try {
        $base = base_path();

        $processes = collect(range(1, 3))->map(function () use ($base) {
            return Process::path($base)->start('php artisan concurrency:process-booster-payouts');
        });

        // Each worker's own "CREDITED:N" count is deliberately NOT summed
        // across processes and asserted here — it's a global paid-schedule
        // count read independently by each of the 3 concurrent processes,
        // so one process's snapshot window can straddle a commit made by a
        // sibling process and report a delta it didn't itself cause. That's
        // a race in the TEST's own measurement, not in the application
        // code under test. The only assertions that actually prove "paid
        // exactly once, never zero, never twice" are the end-state checks
        // below, taken once, after all 3 processes have fully finished.
        $processes->map(fn ($process) => $process->wait());

        expect($schedule->fresh()->status)->toBe('paid');

        $entries = WalletLedgerEntry::where('member_id', $member->id)->where('category', 'booster')->get();
        expect($entries)->toHaveCount(1);
        expect((float) $entries->first()->amount)->toBe(5000.0);
        expect((float) $member->fresh()->wallet_balance)->toBe(5000.0);
    } finally {
        // Deleting the member cascades away booster_qualifications,
        // booster_payout_schedules, and wallet_ledger_entries together
        // (the 2026_09_15_100001 migration fixed a real FK gap this test
        // itself surfaced — see DOMAIN_LOGIC.md §21's T-020 entry).
        $member->delete();
        $user->delete();
    }
});

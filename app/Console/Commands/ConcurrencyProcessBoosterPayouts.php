<?php

namespace App\Console\Commands;

use App\Jobs\ProcessBoosterPayouts;
use App\Models\BoosterPayoutSchedule;
use App\Services\WalletLedgerService;
use Illuminate\Console\Command;

/**
 * T-020 (DOMAIN_LOGIC.md §21) — a single-purpose command launched as a real,
 * separate OS process by
 * tests/Concurrency/BoosterPayoutConcurrencyTest.php to produce a genuine
 * race against ProcessBoosterPayouts' lockForUpdate() + pending-status
 * re-check critical section (§19, T-011). Never registered as user-facing
 * functionality — exists purely as a concurrency-test worker; invokes the
 * real job's handle() once, exactly as one real scheduler tick would.
 */
class ConcurrencyProcessBoosterPayouts extends Command
{
    protected $signature = 'concurrency:process-booster-payouts';

    protected $description = 'Test worker: run one ProcessBoosterPayouts tick, for real-process concurrency testing.';

    public function handle(ProcessBoosterPayouts $job, WalletLedgerService $wallet): int
    {
        $paidBefore = BoosterPayoutSchedule::where('status', 'paid')->count();

        $job->handle($wallet);

        $paidAfter = BoosterPayoutSchedule::where('status', 'paid')->count();

        $this->line('CREDITED:'.($paidAfter - $paidBefore));

        return self::SUCCESS;
    }
}

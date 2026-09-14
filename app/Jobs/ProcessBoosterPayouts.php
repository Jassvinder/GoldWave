<?php

namespace App\Jobs;

use App\Models\BoosterPayoutSchedule;
use App\Services\WalletLedgerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * DOMAIN_LOGIC.md §9.1 step 6 / §19 "Booster Payout Processor" job table
 * entry. Credits every due, still-`pending` `booster_payout_schedules` row
 * through `WalletLedgerService::credit()` and marks it `paid`. Idempotent —
 * the row's own `pending`/`paid` status (checked again under
 * `lockForUpdate()`) guards a retried run, and the schema's
 * `(booster_qualification_id, month_no)` unique constraint means this
 * table can never hold a duplicate month to begin with.
 *
 * **Scope boundary (matches T-005/T-007's precedent):** this task (T-011)
 * builds the processing logic itself; wiring it onto Laravel's scheduler is
 * T-019's job ("Wire scheduled jobs...", `Docs/TASKS.md`).
 */
class ProcessBoosterPayouts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(WalletLedgerService $wallet): void
    {
        $today = now()->startOfDay()->toDateString();

        BoosterPayoutSchedule::where('status', 'pending')
            ->whereDate('scheduled_date', '<=', $today)
            ->each(function (BoosterPayoutSchedule $schedule) use ($wallet) {
                DB::transaction(function () use ($schedule, $wallet) {
                    $locked = BoosterPayoutSchedule::whereKey($schedule->id)->lockForUpdate()->firstOrFail();

                    if ($locked->status !== 'pending') {
                        return;
                    }

                    $qualification = $locked->boosterQualification()->firstOrFail();
                    $member = $qualification->member()->firstOrFail();

                    $entry = $wallet->credit(
                        $member,
                        'booster',
                        (float) $locked->amount,
                        $locked,
                        "Booster level {$qualification->level_no} month {$locked->month_no} payout",
                    );

                    $locked->update(['status' => 'paid', 'wallet_ledger_entry_id' => $entry->id]);
                });
            });
    }
}

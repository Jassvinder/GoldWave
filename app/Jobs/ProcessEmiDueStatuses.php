<?php

namespace App\Jobs;

use App\Models\EmiInstallment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * DOMAIN_LOGIC.md §5 item 7 / §19 "EMI Due Processor" — pure status
 * transitions only, never activates eligibility itself (payment confirmation
 * is the only thing that marks an installment `paid`). Idempotent: re-running
 * this job on the same day is a no-op past the first run, since each
 * transition only selects rows still in the prior status.
 *
 * **Scope boundary:** this task (T-005) builds the transition logic itself;
 * wiring it onto Laravel's scheduler (`bootstrap/app.php`'s `withSchedule`)
 * is T-019's job ("Wire scheduled jobs... with idempotency and retry-safety",
 * `Docs/TASKS.md`), alongside the other §19 jobs. Until T-019 runs it daily,
 * installment 1 of any schedule is generated already correctly `due`/`paid`
 * (`GenerateEmiInstallments`), so the core pay flow is testable without this
 * job — only later installments' `upcoming` → `due` → `overdue` transitions
 * depend on it.
 */
class ProcessEmiDueStatuses implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $today = now()->startOfDay()->toDateString();

        EmiInstallment::where('status', 'upcoming')
            ->whereDate('due_date', '<=', $today)
            ->update(['status' => 'due']);

        EmiInstallment::where('status', 'due')
            ->whereDate('due_date', '<', $today)
            ->update(['status' => 'overdue']);
    }
}

<?php

namespace App\Jobs;

use App\Actions\Draw\ExecuteMonthlyDraw;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * DOMAIN_LOGIC.md §8.4/§8.5/§8.6/§19 "Monthly Draw Executor" job table entry
 * (15th, 12:00) — executes every active group's current cycle month.
 * `ExecuteMonthlyDraw` (T-010) is itself idempotent per
 * `(draw_group_id, cycle_month_no)`, so a retried/duplicate run is a safe
 * no-op.
 *
 * **Scope boundary:** T-010 built the execution logic itself; wiring this
 * job onto Laravel's scheduler is T-019's job ("Wire scheduled jobs...",
 * `Docs/TASKS.md`).
 */
class RunMonthlyDrawExecution implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ExecuteMonthlyDraw $execute): void
    {
        $execute();
    }
}

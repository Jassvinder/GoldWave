<?php

namespace App\Jobs;

use App\Actions\Draw\GenerateDrawGroups;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * DOMAIN_LOGIC.md §8.1/§19 "Draw Group Generator" job table entry (15th,
 * 00:00) — freezes the eligible/ungrouped backlog into as many full groups
 * as it allows. `GenerateDrawGroups` (T-010) is itself idempotent (a member
 * who has ever appeared in any `draw_group_members` row is permanently
 * excluded from future runs), so a retried/duplicate run is a safe no-op.
 *
 * **Scope boundary:** T-010 built the grouping logic itself; wiring this job
 * onto Laravel's scheduler is T-019's job ("Wire scheduled jobs...",
 * `Docs/TASKS.md`).
 */
class RunDrawGroupGeneration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(GenerateDrawGroups $generate): void
    {
        $generate();
    }
}

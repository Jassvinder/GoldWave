<?php

namespace App\Jobs;

use App\Actions\DummyEntries\GenerateDailyDummyEntries;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * DOMAIN_LOGIC.md §19 "Daily Company Direct Generator" job table entry.
 *
 * **Scope boundary (matches T-005/T-007/T-011's precedent):** this task
 * (T-013) builds the generation logic itself; wiring this job onto Laravel's
 * scheduler to actually run daily is T-019's job ("Wire scheduled jobs...",
 * `Docs/TASKS.md`).
 */
class RunDailyDummyEntryGeneration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(GenerateDailyDummyEntries $generate): void
    {
        $generate();
    }
}

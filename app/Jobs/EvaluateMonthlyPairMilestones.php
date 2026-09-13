<?php

namespace App\Jobs;

use App\Actions\Compensation\EvaluatePairMilestones;
use App\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * DOMAIN_LOGIC.md §7.3 "Pair income is calculated at the end of every month"
 * / §19 job table. Runs `EvaluatePairMilestones` for every beneficiary who
 * has at least one unused `pair_entries` row — a member with none has
 * nothing to evaluate.
 *
 * **Scope boundary (matches T-005's `ProcessEmiDueStatuses` precedent):** this
 * task (T-007) builds the evaluation logic itself; wiring this job onto
 * Laravel's scheduler to actually run monthly is T-019's job ("Wire scheduled
 * jobs...", `Docs/TASKS.md`), alongside the other §19 jobs.
 */
class EvaluateMonthlyPairMilestones implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly ?Carbon $forMonth = null) {}

    public function handle(EvaluatePairMilestones $evaluate): void
    {
        $forMonth = $this->forMonth ?? Carbon::now();

        Member::query()
            ->whereHas('pairEntries', fn ($q) => $q->where('status', 'unused'))
            ->each(function (Member $beneficiary) use ($evaluate, $forMonth) {
                $evaluate($beneficiary, $forMonth);
            });
    }
}

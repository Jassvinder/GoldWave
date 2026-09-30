<?php

namespace App\Console\Commands;

use App\Jobs\EvaluateMonthlyPairMilestones;
use App\Jobs\RunDrawGroupGeneration;
use App\Models\DrawGroup;
use App\Models\PairRewardTransaction;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

/**
 * Runs, right now and synchronously, the two period jobs the scheduler would otherwise only fire on their calendar day
 * (`App\Console\Scheduling`): the month-end Pair/Reward evaluation (§7.3) and the 15th's Draw Group generation (§8.1).
 * It dispatches the very same Job classes the scheduler uses — no separate logic — so the result is exactly what the
 * real calendar run would produce. Both jobs are idempotent: a milestone is never paid twice and a member is never
 * grouped twice. Draw execution is deliberately a separate command (`jobs:draw`), since each run of it draws a new month.
 */
class RunMonthEndJobs extends Command
{
    use ConfirmableTrait;

    protected $signature = 'jobs:month-end {--force : run in production without asking}';

    protected $description = 'Run the month-end Pair/Reward evaluation and the Draw Group generation now (same jobs the scheduler runs).';

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $lastRewardId = (int) (PairRewardTransaction::max('id') ?? 0);
        EvaluateMonthlyPairMilestones::dispatchSync();

        $rewards = PairRewardTransaction::with('member')->where('id', '>', $lastRewardId)->orderBy('id')->get();
        $this->info("Pair/Reward evaluation done — {$rewards->count()} milestone(s) paid.");

        foreach ($rewards as $reward) {
            $this->line(sprintf(
                '  %s  milestone %d  (L%d / R%d)  ₹%s',
                $reward->member->customer_id,
                $reward->milestone_no,
                $reward->left_consumed_count,
                $reward->right_consumed_count,
                number_format((float) $reward->reward_amount, 2),
            ));
        }

        $lastGroupId = (int) (DrawGroup::max('id') ?? 0);
        RunDrawGroupGeneration::dispatchSync();

        $groups = DrawGroup::withCount('members')->where('id', '>', $lastGroupId)->orderBy('id')->get();
        $this->info("Draw Group generation done — {$groups->count()} new group(s).");

        foreach ($groups as $group) {
            $this->line("  Group #{$group->group_no}  {$group->members_count} members");
        }

        return self::SUCCESS;
    }
}

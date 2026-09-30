<?php

namespace App\Console\Commands;

use App\Jobs\RunMonthlyDrawExecution;
use App\Models\DrawExecution;
use App\Models\DrawGroup;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

/**
 * Runs the 15th's Monthly Draw (§8.4–§8.6) right now, through the same Job the scheduler uses. Every active group draws
 * its *next* cycle month, so each run of this command is one more month's draw — run it once per simulated month. A
 * month with no prize of its own uses the Silver/Gold default from Draw Settings, so no group is skipped for want of a prize.
 */
class RunDrawExecution extends Command
{
    use ConfirmableTrait;

    protected $signature = 'jobs:draw {--force : run in production without asking}';

    protected $description = 'Run the Monthly Draw now for every active group (each run draws the next cycle month).';

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $lastExecutionId = (int) (DrawExecution::max('id') ?? 0);
        RunMonthlyDrawExecution::dispatchSync(advanceOneMonth: true);

        $executions = DrawExecution::with(['drawGroup', 'winner', 'uplineBenefitMember'])
            ->where('id', '>', $lastExecutionId)->orderBy('id')->get();
        $this->info("Monthly Draw done — {$executions->count()} group(s) drawn.");

        foreach ($executions as $execution) {
            $upline = $execution->uplineBenefitMember?->customer_id;
            $prize = $execution->prizeConfig();
            $this->line(sprintf(
                '  Group #%d  month %d  winner %s  prize %s (₹%s)%s',
                $execution->drawGroup->group_no,
                $execution->cycle_month_no,
                $execution->winner->customer_id,
                $prize->prize_name ?? '—',
                $prize->prize_value ?? '—',
                $upline ? "  (upline benefit: {$upline})" : '',
            ));
        }

        $drawnGroupIds = $executions->pluck('draw_group_id')->all();
        $skipped = DrawGroup::where('status', 'active')->whereNotIn('id', $drawnGroupIds)->orderBy('group_no')->pluck('group_no');

        if ($skipped->isNotEmpty()) {
            $this->warn('Not drawn (no members left in the draw): group #'.$skipped->implode(', #'));
        }

        return self::SUCCESS;
    }
}

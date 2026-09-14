<?php

namespace App\Actions\Draw;

use App\Events\DrawResultPublished;
use App\Models\DrawExecution;
use App\Models\DrawGroup;
use App\Models\DrawGroupMonthConfig;
use Illuminate\Support\Facades\DB;

/**
 * DOMAIN_LOGIC.md §8.4/§8.5/§8.6 — for every active group whose current
 * cycle month has a configured prize (Super Admin Settings, not built by
 * this task — DOMAIN_LOGIC.md §21 T-010 pre-coding pass), securely selects
 * one remaining eligible member as winner, removes them from the group's
 * pool, evaluates the upline draw benefit, and fires `DrawResultPublished`.
 * Idempotent per (draw_group_id, cycle_month_no) via an upfront exists()
 * check plus the DB unique constraint, mirroring
 * `Actions/Compensation/CalculateLevelIncome`'s pattern.
 */
class ExecuteMonthlyDraw
{
    /** @return array<int, DrawExecution> */
    public function __invoke(): array
    {
        $executions = [];

        foreach (DrawGroup::where('status', 'active')->get() as $group) {
            $execution = $this->executeForGroup($group);

            if ($execution) {
                $executions[] = $execution;
            }
        }

        return $executions;
    }

    private function executeForGroup(DrawGroup $group): ?DrawExecution
    {
        $cycleMonthNo = $group->executions()->count() + 1;

        if ($cycleMonthNo > 20) {
            return null; // Already completed — status should already reflect this.
        }

        if (DrawExecution::where('draw_group_id', $group->id)->where('cycle_month_no', $cycleMonthNo)->exists()) {
            return null; // Already executed this month — idempotent no-op.
        }

        $monthConfig = DrawGroupMonthConfig::where('draw_group_id', $group->id)
            ->where('cycle_month_no', $cycleMonthNo)
            ->first();

        if (! $monthConfig) {
            return null; // Prize not configured yet — never invent a value.
        }

        $pool = $group->members()->where('is_winner_removed', false)->orderBy('id')->get();

        if ($pool->isEmpty()) {
            return null;
        }

        $execution = DB::transaction(function () use ($group, $cycleMonthNo, $pool) {
            $index = random_int(0, $pool->count() - 1);
            $winnerLink = $pool[$index];
            $winner = $winnerLink->member()->firstOrFail();

            $rngProof = json_encode([
                'algorithm' => 'random_int',
                'pool_member_ids' => $pool->pluck('member_id')->all(),
                'selected_index' => $index,
                'selected_member_id' => $winner->id,
            ]);

            $uplineBenefitMemberId = null;
            $sponsor = $winner->sponsor;

            if ($sponsor && $sponsor->directs()->count() >= 10) {
                $uplineBenefitMemberId = $sponsor->id;
            }

            $execution = DrawExecution::create([
                'draw_group_id' => $group->id,
                'cycle_month_no' => $cycleMonthNo,
                'executed_at' => now(),
                'winner_member_id' => $winner->id,
                'rng_proof' => $rngProof,
                'upline_benefit_member_id' => $uplineBenefitMemberId,
                'status' => 'executed',
            ]);

            $winnerLink->update(['is_winner_removed' => true]);

            if ($cycleMonthNo === 20) {
                $group->update(['status' => 'completed']);
            }

            return $execution;
        });

        event(new DrawResultPublished($execution));

        return $execution;
    }
}

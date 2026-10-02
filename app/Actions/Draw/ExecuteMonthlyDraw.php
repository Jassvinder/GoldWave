<?php

namespace App\Actions\Draw;

use App\Events\DrawResultPublished;
use App\Models\DrawExecution;
use App\Models\DrawGroup;
use App\Services\DrawPrizeResolver;
use Illuminate\Support\Facades\DB;

/**
 * DOMAIN_LOGIC.md §8.4/§8.5/§8.6 — for every active group, securely selects
 * the month's winners (usually one; a month's prize can ask for more — §8.3,
 * 30-09-2026) from the members who haven't won yet, removes each from the
 * group's pool, evaluates the upline draw benefit per winner, and fires
 * `DrawResultPublished` per winner. The month's prize is frozen on the draw
 * (`DrawPrizeResolver::snapshot()`): the group's own prize if set, else the
 * Silver/Gold default — a missing prize never holds a draw back.
 * Idempotent per (draw_group_id, cycle_month_no) via an upfront exists()
 * check plus the DB unique constraint on (group, month, winner_no).
 */
class ExecuteMonthlyDraw
{
    public const CYCLE_MONTHS = 20;

    public function __construct(private readonly DrawPrizeResolver $prizes) {}

    /**
     * @param  bool  $oncePerCalendarMonth  the scheduled 15th draw never draws a group twice in one calendar month;
     *                                      only the manual `jobs:draw` test command turns this off to advance a month per run
     * @return array<int, DrawExecution>
     */
    public function __invoke(bool $oncePerCalendarMonth = true): array
    {
        $executions = [];

        foreach (DrawGroup::where('status', 'active')->get() as $group) {
            array_push($executions, ...$this->executeForGroup($group, $oncePerCalendarMonth));
        }

        return $executions;
    }

    /** @return list<DrawExecution> */
    private function executeForGroup(DrawGroup $group, bool $oncePerCalendarMonth): array
    {
        // Every month has a prize, so this guard is what keeps a re-run on the 15th from drawing next month early.
        if ($oncePerCalendarMonth && $group->executions()->where('executed_at', '>=', now()->startOfMonth())->exists()) {
            return [];
        }

        $cycleMonthNo = ((int) $group->executions()->max('cycle_month_no')) + 1;

        if ($cycleMonthNo > self::CYCLE_MONTHS) {
            return []; // Already completed — status should already reflect this.
        }

        if (DrawExecution::where('draw_group_id', $group->id)->where('cycle_month_no', $cycleMonthNo)->exists()) {
            return []; // Already executed this month — idempotent no-op.
        }

        $pool = $group->members()->where('is_winner_removed', false)->orderBy('id')->get();

        if ($pool->isEmpty()) {
            return [];
        }

        $executions = DB::transaction(function () use ($group, $cycleMonthNo, $pool) {
            $prize = $this->prizes->snapshot($group, $cycleMonthNo);
            $winnersToPick = min(max(1, (int) $prize->winners_count), $pool->count());
            $executions = [];

            for ($winnerNo = 1; $winnerNo <= $winnersToPick; $winnerNo++) {
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

                // The company root / an unassigned dummy never receives it (it is never a beneficiary), and since T-174 nor
                // does an entry inserted under the root (Pair/Reward and Booster only).
                if ($sponsor && ! $sponsor->isExcludedFromGeneralIncome() && $sponsor->directs()->count() >= 10) {
                    $uplineBenefitMemberId = $sponsor->id;
                }

                $executions[] = DrawExecution::create([
                    'draw_group_id' => $group->id,
                    'cycle_month_no' => $cycleMonthNo,
                    'winner_no' => $winnerNo,
                    'executed_at' => now(),
                    'winner_member_id' => $winner->id,
                    'rng_proof' => $rngProof,
                    'upline_benefit_member_id' => $uplineBenefitMemberId,
                    'status' => 'executed',
                ]);

                $winnerLink->update(['is_winner_removed' => true]);
                // A later winner of the same month is picked from the members still left.
                $pool = $pool->forget($index)->values();
            }

            if ($cycleMonthNo === self::CYCLE_MONTHS || $pool->isEmpty()) {
                $group->update(['status' => 'completed']);
            }

            return $executions;
        });

        foreach ($executions as $execution) {
            event(new DrawResultPublished($execution));
        }

        return $executions;
    }
}

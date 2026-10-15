<?php

namespace App\Actions\Draw;

use App\Events\DrawResultPublished;
use App\Models\DrawExecution;
use App\Models\DrawGroup;
use App\Models\DrawGroupMember;
use App\Models\User;
use App\Services\DrawPrizeResolver;
use App\Services\RuleVersionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
 *
 * §8.4 "Manual draw" (15-10-2026, T-221): with the automatic draw switched off in Draw Settings, the draw is held in
 * person and Super Admin/Admin enters the drawn Customer IDs (`recordManual()`). A manual winner is recorded exactly
 * like a random one — same prize snapshot, pool removal, upline benefit and event — only the proof says "manual".
 */
class ExecuteMonthlyDraw
{
    public const CYCLE_MONTHS = 20;

    public function __construct(private readonly DrawPrizeResolver $prizes) {}

    /** Draw Settings' "Automatic draw on the 15th" switch (`draw_auto_enabled`, on unless Super Admin turns it off). */
    public static function autoDrawEnabled(RuleVersionService $rules): bool
    {
        return (bool) $rules->value('draw_auto_enabled', true);
    }

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

    /**
     * Records a draw held in person: the given Customer IDs are this group's winners for its next cycle month, in the
     * order given. Refused (nothing saved) unless the group is active, has no draw yet this calendar month, and every
     * ID is a different member still in the group's draw — exactly as many as the month's prize asks for.
     *
     * @param  list<string>  $customerIds
     * @return list<DrawExecution>
     */
    public function recordManual(DrawGroup $group, array $customerIds, User $operator): array
    {
        $customerIds = array_map(fn (string $id): string => strtoupper(trim($id)), $customerIds);

        $executions = DB::transaction(function () use ($group, $customerIds, $operator) {
            $group = DrawGroup::whereKey($group->id)->lockForUpdate()->firstOrFail();

            if ($group->status !== 'active') {
                throw ValidationException::withMessages(['customer_ids' => "Group #{$group->group_no} has no draws left."]);
            }

            if ($this->drawnThisCalendarMonth($group)) {
                throw ValidationException::withMessages(['customer_ids' => "Group #{$group->group_no} already has its draw for this month."]);
            }

            $cycleMonthNo = ((int) $group->executions()->max('cycle_month_no')) + 1;
            $pool = $group->members()->where('is_winner_removed', false)->with('member')->orderBy('id')->get();
            $prize = $this->prizes->snapshot($group, $cycleMonthNo);
            $expected = min(max(1, (int) $prize->winners_count), $pool->count());

            if (count($customerIds) !== $expected) {
                throw ValidationException::withMessages([
                    'customer_ids' => "Month {$cycleMonthNo} needs exactly {$expected} winner".($expected === 1 ? '' : 's').'.',
                ]);
            }

            $byCustomerId = $pool->keyBy(fn (DrawGroupMember $link): string => strtoupper((string) $link->member->customer_id));
            $errors = [];

            foreach ($customerIds as $index => $customerId) {
                if ($customerId === '') {
                    $errors["customer_ids.{$index}"] = 'Enter the Customer ID drawn.';
                } elseif (array_search($customerId, $customerIds, true) !== $index) {
                    $errors["customer_ids.{$index}"] = "{$customerId} is entered twice.";
                } elseif (! $byCustomerId->has($customerId)) {
                    $errors["customer_ids.{$index}"] = "{$customerId} is not in Group #{$group->group_no}'s draw (not in this group, or has already won).";
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $poolMemberIds = $pool->pluck('member_id')->all();
            $executions = [];

            foreach ($customerIds as $index => $customerId) {
                $proof = json_encode([
                    'algorithm' => 'manual',
                    'pool_member_ids' => $poolMemberIds,
                    'entered_customer_id' => $customerId,
                    'entered_by_user_id' => $operator->id,
                    'entered_by' => $operator->name,
                ]);

                $executions[] = $this->recordWinner($group, $cycleMonthNo, $index + 1, $byCustomerId->get($customerId), (string) $proof);
            }

            $this->completeIfFinished($group, $cycleMonthNo, $pool->count() - $expected);

            return $executions;
        });

        foreach ($executions as $execution) {
            event(new DrawResultPublished($execution));
        }

        return $executions;
    }

    /** One draw per group per calendar month (§8.3), for the scheduled and the manual draw alike. */
    public function drawnThisCalendarMonth(DrawGroup $group): bool
    {
        return $group->executions()->where('executed_at', '>=', now()->startOfMonth())->exists();
    }

    /** @return list<DrawExecution> */
    private function executeForGroup(DrawGroup $group, bool $oncePerCalendarMonth): array
    {
        // Every month has a prize, so this guard is what keeps a re-run on the 15th from drawing next month early.
        if ($oncePerCalendarMonth && $this->drawnThisCalendarMonth($group)) {
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

                $rngProof = json_encode([
                    'algorithm' => 'random_int',
                    'pool_member_ids' => $pool->pluck('member_id')->all(),
                    'selected_index' => $index,
                    'selected_member_id' => $winnerLink->member_id,
                ]);

                $executions[] = $this->recordWinner($group, $cycleMonthNo, $winnerNo, $winnerLink, (string) $rngProof);
                // A later winner of the same month is picked from the members still left.
                $pool = $pool->forget($index)->values();
            }

            $this->completeIfFinished($group, $cycleMonthNo, $pool->count());

            return $executions;
        });

        foreach ($executions as $execution) {
            event(new DrawResultPublished($execution));
        }

        return $executions;
    }

    /** Saves one winner, removes them from the group's pool and evaluates the upline draw benefit (§8.5). */
    private function recordWinner(DrawGroup $group, int $cycleMonthNo, int $winnerNo, DrawGroupMember $winnerLink, string $proof): DrawExecution
    {
        $winner = $winnerLink->member()->firstOrFail();
        $uplineBenefitMemberId = null;
        $sponsor = $winner->sponsor;

        // The company root / an unassigned dummy never receives it (it is never a beneficiary), and since T-174 nor
        // does an entry inserted under the root (Pair/Reward and Booster only).
        if ($sponsor && ! $sponsor->isExcludedFromGeneralIncome() && $sponsor->directs()->count() >= 10) {
            $uplineBenefitMemberId = $sponsor->id;
        }

        $execution = DrawExecution::create([
            'draw_group_id' => $group->id,
            'cycle_month_no' => $cycleMonthNo,
            'winner_no' => $winnerNo,
            'executed_at' => now(),
            'winner_member_id' => $winner->id,
            'rng_proof' => $proof,
            'upline_benefit_member_id' => $uplineBenefitMemberId,
            'status' => 'executed',
        ]);

        $winnerLink->update(['is_winner_removed' => true]);

        return $execution;
    }

    private function completeIfFinished(DrawGroup $group, int $cycleMonthNo, int $membersLeft): void
    {
        if ($cycleMonthNo === self::CYCLE_MONTHS || $membersLeft === 0) {
            $group->update(['status' => 'completed']);
        }
    }
}

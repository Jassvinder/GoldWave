<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\PairPoolBreakdown;
use App\Services\PairQualifiedDirects;
use App\Services\RuleVersionService;
use App\Support\Dates;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md M11 — progress, milestones (with each achieved milestone's reward and date), consumed/available business (DOMAIN_LOGIC.md §7). */
class PairRewardController extends Controller
{
    public function __construct(
        private readonly RuleVersionService $rules,
        private readonly PairPoolBreakdown $pairPool,
        private readonly PairQualifiedDirects $qualifiedDirects,
    ) {}

    public function index(Request $request): Response
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        $pool = $this->pairPool->forMember($member);

        /** @var list<array{milestone_no: int, name?: string, min_directs: int, left: int, right: int}> $milestoneRules */
        $milestoneRules = $this->rules->value('pair_milestones', []);

        // T-125 — one row per milestone, with this member's Reward/Date filled in once that milestone is actually achieved
        // (there is no separate Reward History list any more).
        $rewardsByMilestone = $member->pairRewardTransactions()->get()->keyBy('milestone_no');

        $milestones = array_map(function (array $milestone) use ($rewardsByMilestone, $pool): array {
            $reward = $rewardsByMilestone->get($milestone['milestone_no']);
            $used = $pool['consumed_by_milestone'][$milestone['milestone_no']] ?? null;

            return [
                // How many of this member's entries each achieved milestone actually consumed.
                'used_left' => $used['left'] ?? null,
                'used_right' => $used['right'] ?? null,
                'milestone_no' => $milestone['milestone_no'],
                'name' => $milestone['name'] ?? "Milestone #{$milestone['milestone_no']}",
                'min_directs' => $milestone['min_directs'],
                'left' => $milestone['left'],
                'right' => $milestone['right'],
                'reward_amount' => $reward?->reward_amount,
                'achieved_on' => $reward !== null ? Dates::date($reward->calculated_for_month) : null,
            ];
        }, $milestoneRules);

        $nextMilestone = collect($milestones)->first(fn (array $milestone): bool => $milestone['reward_amount'] === null);

        return Inertia::render('member/pair-reward', [
            'progress' => [
                'unused_left' => $pool['left']['unused'],
                'unused_right' => $pool['right']['unused'],
                'consumed_left' => $pool['left']['consumed'],
                'consumed_right' => $pool['right']['consumed'],
                // T-178 — counted against each milestone's total Min Directs (DOMAIN_LOGIC.md §7.3).
                'qualified_directs' => $this->qualifiedDirects->count($member),
            ],
            'pool' => [
                'left' => $pool['left'],
                'right' => $pool['right'],
                'awaiting_by_plan' => $pool['awaiting_by_plan'],
            ],
            'milestones' => $milestones,
            'next_milestone' => $nextMilestone,
        ]);
    }
}

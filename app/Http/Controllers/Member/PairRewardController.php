<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\RuleVersionService;
use App\Support\Dates;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md M11 — progress, milestones (with each achieved milestone's reward and date), consumed/available business (DOMAIN_LOGIC.md §7). */
class PairRewardController extends Controller
{
    public function __construct(private readonly RuleVersionService $rules) {}

    public function index(Request $request): Response
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        $unusedLeft = $member->pairEntries()->where('side', 'left')->where('status', 'unused')->count();
        $unusedRight = $member->pairEntries()->where('side', 'right')->where('status', 'unused')->count();
        $consumedLeft = $member->pairEntries()->where('side', 'left')->where('status', 'consumed')->count();
        $consumedRight = $member->pairEntries()->where('side', 'right')->where('status', 'consumed')->count();

        /** @var list<array{milestone_no: int, name?: string, min_directs: int, left: int, right: int}> $milestoneRules */
        $milestoneRules = $this->rules->value('pair_milestones', []);

        // T-125 — one row per milestone, with this member's Reward/Date filled in once that milestone is actually achieved
        // (there is no separate Reward History list any more).
        $rewardsByMilestone = $member->pairRewardTransactions()->get()->keyBy('milestone_no');

        $milestones = array_map(function (array $milestone) use ($rewardsByMilestone): array {
            $reward = $rewardsByMilestone->get($milestone['milestone_no']);

            return [
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
                'unused_left' => $unusedLeft,
                'unused_right' => $unusedRight,
                'consumed_left' => $consumedLeft,
                'consumed_right' => $consumedRight,
            ],
            'milestones' => $milestones,
            'next_milestone' => $nextMilestone,
        ]);
    }
}

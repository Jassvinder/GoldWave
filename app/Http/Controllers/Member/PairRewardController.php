<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\PairRewardTransaction;
use App\Services\RuleVersionService;
use App\Support\Dates;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md M11 — progress, milestones, consumed/available business, reward history (DOMAIN_LOGIC.md §7). */
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

        /** @var list<array{milestone_no: int, min_directs: int, left: int, right: int}> $milestones */
        $milestones = $this->rules->value('pair_milestones', []);
        $achievedMilestoneNos = PairRewardTransaction::where('member_id', $member->id)->pluck('milestone_no')->all();

        $nextMilestone = null;

        foreach ($milestones as $milestone) {
            if (! in_array($milestone['milestone_no'], $achievedMilestoneNos, true)) {
                $nextMilestone = $milestone;
                break;
            }
        }

        $rewards = $member->pairRewardTransactions()
            ->orderByDesc('id')
            ->get()
            ->map($this->mapReward(...));

        return Inertia::render('member/pair-reward', [
            'progress' => [
                'unused_left' => $unusedLeft,
                'unused_right' => $unusedRight,
                'consumed_left' => $consumedLeft,
                'consumed_right' => $consumedRight,
            ],
            'milestones' => $milestones,
            'next_milestone' => $nextMilestone,
            'rewards' => $rewards,
        ]);
    }

    /** @return array<string, mixed> */
    private function mapReward(PairRewardTransaction $reward): array
    {
        return [
            'milestone_no' => $reward->milestone_no,
            'left_consumed_count' => $reward->left_consumed_count,
            'right_consumed_count' => $reward->right_consumed_count,
            'reward_amount' => $reward->reward_amount,
            'calculated_for_month' => Dates::date($reward->calculated_for_month),
        ];
    }
}

<?php

namespace App\Http\Controllers\Member;

use App\Actions\Draw\ExecuteMonthlyDraw;
use App\Http\Controllers\Controller;
use App\Models\DrawExecution;
use App\Models\DrawGroup;
use App\Models\DrawGroupMember;
use App\Models\DrawGroupMonthConfig;
use App\Models\Member;
use App\Services\DrawPrizeResolver;
use App\Support\Dates;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * INSTRUCTIONS.md M13 — own draw groups, member list, winner history (DOMAIN_LOGIC.md §8).
 * T-199 — every winner row says when their Sponsor also received the prize, and the member sees every upline benefit
 * they received (from any group). T-200 — each group states its number, Customer ID range, draws held and next prize.
 */
class DrawController extends Controller
{
    public function index(Request $request, DrawPrizeResolver $prizes): Response
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        $groupIds = DrawGroupMember::where('member_id', $member->id)->pluck('draw_group_id');

        $groups = DrawGroup::whereIn('id', $groupIds)
            ->with(['members.member.user', 'executions.winner.user', 'executions.uplineBenefitMember.user', 'monthConfigs'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (DrawGroup $group): array => $this->mapGroup($group, $member, $prizes));

        $uplineBenefits = DrawExecution::with(['drawGroup.monthConfigs', 'winner.user'])
            ->where('upline_benefit_member_id', $member->id)
            ->orderByDesc('executed_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (DrawExecution $execution): array => [
                'id' => $execution->id,
                'group_no' => $execution->drawGroup->group_no,
                'cycle_month_no' => $execution->cycle_month_no,
                'executed_at' => Dates::date($execution->executed_at),
                'winner_customer_id' => $execution->winner->customer_id,
                'winner_name' => $execution->winner->user?->name,
                ...$this->prizeOf($execution->drawGroup->monthConfigs->firstWhere('cycle_month_no', $execution->cycle_month_no)),
            ]);

        return Inertia::render('member/draw', [
            'groups' => $groups,
            'upline_benefits' => $uplineBenefits,
        ]);
    }

    /** @return array<string, mixed> */
    private function mapGroup(DrawGroup $group, Member $viewer, DrawPrizeResolver $prizes): array
    {
        $ordered = $group->members->sortBy('member_id')->values();
        $drawsHeld = (int) $group->executions->max('cycle_month_no');
        $nextMonth = $drawsHeld + 1;
        $nextPrize = $nextMonth <= ExecuteMonthlyDraw::CYCLE_MONTHS
            ? ($group->monthConfigs->firstWhere('cycle_month_no', $nextMonth) !== null
                ? $this->prizeOf($group->monthConfigs->firstWhere('cycle_month_no', $nextMonth))
                : ['prize_name' => $prizes->defaultFor($nextMonth)['prize_name'], 'prize_value' => number_format($prizes->defaultFor($nextMonth)['prize_value'], 2, '.', '')])
            : null;

        return [
            'id' => $group->id,
            'group_no' => $group->group_no,
            'status' => $group->status,
            'size' => $ordered->count(),
            'first_customer_id' => $ordered->first()?->member->customer_id,
            'last_customer_id' => $ordered->last()?->member->customer_id,
            'winners_count' => $group->members->where('is_winner_removed', true)->count(),
            'draws_held' => $drawsHeld,
            'cycle_months' => ExecuteMonthlyDraw::CYCLE_MONTHS,
            'next_draw' => $nextPrize === null ? null : ['cycle_month_no' => $nextMonth, ...$nextPrize],
            // Members still in the draw; winners are listed separately with their prize.
            'members' => $group->members->where('is_winner_removed', false)->values()->map($this->mapMember(...))->all(),
            'executions' => $group->executions->sortBy([['cycle_month_no', 'asc'], ['winner_no', 'asc']])->values()->map(fn (DrawExecution $execution): array => $this->mapExecution($execution, $viewer, $group->monthConfigs->firstWhere('cycle_month_no', $execution->cycle_month_no)))->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function mapMember(DrawGroupMember $groupMember): array
    {
        return [
            'customer_id' => $groupMember->member->customer_id,
            'name' => $groupMember->member->user?->name,
            'is_winner_removed' => $groupMember->is_winner_removed,
        ];
    }

    /** @return array<string, mixed> */
    private function mapExecution(DrawExecution $execution, Member $viewer, ?DrawGroupMonthConfig $prize): array
    {
        return [
            'id' => $execution->id,
            'cycle_month_no' => $execution->cycle_month_no,
            'winner_name' => $execution->winner->user?->name,
            ...$this->prizeOf($prize),
            'executed_at' => Dates::date($execution->executed_at),
            'winner_customer_id' => $execution->winner->customer_id,
            'is_own_win' => $execution->winner_member_id === $viewer->id,
            // T-199 — the winner's Sponsor (10+ directs) received the same prize.
            'upline_benefit_customer_id' => $execution->uplineBenefitMember?->customer_id,
            'upline_benefit_name' => $execution->uplineBenefitMember?->user?->name,
            'is_own_upline_benefit' => $execution->upline_benefit_member_id === $viewer->id,
        ];
    }

    /** @return array{prize_name: string|null, prize_value: string|null} */
    private function prizeOf(?DrawGroupMonthConfig $prize): array
    {
        return [
            'prize_name' => $prize?->prize_name,
            'prize_value' => $prize?->prize_value,
        ];
    }
}

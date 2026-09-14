<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\DrawExecution;
use App\Models\DrawGroup;
use App\Models\DrawGroupMember;
use App\Models\Member;
use App\Support\Dates;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md M13 — own draw groups, member list, winner history (DOMAIN_LOGIC.md §8). */
class DrawController extends Controller
{
    public function index(Request $request): Response
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        $groupIds = DrawGroupMember::where('member_id', $member->id)->pluck('draw_group_id');

        $groups = DrawGroup::whereIn('id', $groupIds)
            ->with(['members.member.user', 'executions.winner'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (DrawGroup $group): array => $this->mapGroup($group, $member));

        return Inertia::render('member/draw', [
            'groups' => $groups,
        ]);
    }

    /** @return array<string, mixed> */
    private function mapGroup(DrawGroup $group, Member $viewer): array
    {
        return [
            'id' => $group->id,
            'group_no' => $group->group_no,
            'status' => $group->status,
            'members' => $group->members->map($this->mapMember(...))->all(),
            'executions' => $group->executions->map(fn (DrawExecution $execution): array => $this->mapExecution($execution, $viewer))->all(),
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
    private function mapExecution(DrawExecution $execution, Member $viewer): array
    {
        return [
            'cycle_month_no' => $execution->cycle_month_no,
            'executed_at' => Dates::date($execution->executed_at),
            'winner_customer_id' => $execution->winner->customer_id,
            'is_own_win' => $execution->winner_member_id === $viewer->id,
        ];
    }
}

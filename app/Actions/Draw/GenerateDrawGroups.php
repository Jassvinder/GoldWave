<?php

namespace App\Actions\Draw;

use App\Models\DrawGroup;
use App\Models\DrawGroupMember;
use App\Models\Member;
use App\Services\RuleVersionService;
use Illuminate\Support\Facades\DB;

/**
 * DOMAIN_LOGIC.md §8.1/§8.2 — forms as many full draw groups as the eligible,
 * not-yet-grouped backlog allows in one run (never a partial group —
 * DOMAIN_LOGIC.md §21 T-010 pre-coding pass, user-confirmed: leftovers roll
 * over to a future run). Ordered by `members.id` (equivalent to Customer ID
 * sequence, avoids a lexicographic string-sort bug on IDs like "GWL10" vs
 * "GWL2"). Idempotent: a member who has ever appeared in any
 * `draw_group_members` row is permanently excluded from future runs — a
 * one-lifetime-shot design (§21).
 */
class GenerateDrawGroups
{
    public function __construct(
        private readonly RuleVersionService $rules,
    ) {}

    /** @return array<int, DrawGroup> */
    public function __invoke(): array
    {
        $groupSize = max(1, (int) $this->rules->value('draw_group_size', 200));
        $eligibleMemberIds = $this->eligibleUngroupedMemberIds();
        $nextGroupNo = ((int) DrawGroup::max('group_no')) + 1;

        $createdGroups = [];

        foreach (array_chunk($eligibleMemberIds, $groupSize) as $chunk) {
            if (count($chunk) < $groupSize) {
                break; // Leftover — wait for a future run to complete the batch.
            }

            $createdGroups[] = DB::transaction(function () use ($chunk, $groupSize, &$nextGroupNo) {
                $group = DrawGroup::create([
                    'group_no' => $nextGroupNo++,
                    'size' => $groupSize,
                    'cycle_started_month' => now()->startOfMonth()->toDateString(),
                    'status' => 'active',
                ]);

                foreach ($chunk as $memberId) {
                    DrawGroupMember::create([
                        'draw_group_id' => $group->id,
                        'member_id' => $memberId,
                        'is_winner_removed' => false,
                    ]);
                }

                return $group;
            });
        }

        return $createdGroups;
    }

    /** @return array<int, int> */
    private function eligibleUngroupedMemberIds(): array
    {
        $emiThresholds = $this->rules->value('draw_eligibility_emis', []);

        return Member::query()
            ->where('status', 'active')
            ->where(function ($query) {
                $query->where('is_company_dummy', false)->orWhere('dummy_status', 'assigned');
            })
            ->whereDoesntHave('drawGroupMemberships')
            ->with(['membershipPlan', 'emiSchedule.installments'])
            ->orderBy('id')
            ->get()
            ->filter(fn (Member $member) => $this->isDrawEligible($member, $emiThresholds))
            ->pluck('id')
            ->all();
    }

    /** @param array<string, int> $emiThresholds */
    private function isDrawEligible(Member $member, array $emiThresholds): bool
    {
        $plan = $member->membershipPlan;

        if (! $plan) {
            return false;
        }

        if (! $plan->isEmiPlan()) {
            return true; // Active already implies the one-time plan is fully paid.
        }

        $schedule = $member->emiSchedule;

        if (! $schedule) {
            return false;
        }

        $required = (int) ($emiThresholds[$plan->code] ?? PHP_INT_MAX);
        $completed = $schedule->installments->where('status', 'paid')->count();

        return $completed >= $required;
    }
}

<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MembershipPlan;
use Illuminate\Support\Facades\DB;

/**
 * 28-09-2026, user-requested — explains why a member's unused Pair/Reward
 * entries (DOMAIN_LOGIC.md §7) are far fewer than their team size: every
 * Binary Position downline member on each leg falls into exactly one of
 *
 * - `unused` / `consumed` — has become a fully eligible joining, so it
 *   created one `pair_entries` row for this member (consumed ones are split
 *   per milestone in `consumed_by_milestone`);
 * - `awaiting` — active, but not yet a fully eligible joining: an EMI plan
 *   that hasn't reached its `pair_qualification_emis` count (§7.3), split per
 *   plan in `awaiting_by_plan`;
 * - `inactive` — a real member whose status isn't `active`;
 * - `dummy` — an unassigned company dummy entry (never pays, never counts).
 *
 * Read-only and display-only — `CreatePairEntries`/`EvaluatePairMilestones`
 * never read this. Leg totals use the same subtree as `MemberNetworkSummary`,
 * so `team` here always equals the team size shown elsewhere.
 *
 * @phpstan-type Side array{team: int, unused: int, consumed: int, awaiting: int, inactive: int, dummy: int}
 * @phpstan-type Breakdown array{
 *     left: Side,
 *     right: Side,
 *     consumed_by_milestone: array<int, array{left: int, right: int}>,
 *     awaiting_by_plan: list<array{plan_name: string, required_emis: int|null, left: int, right: int}>
 * }
 */
class PairPoolBreakdown
{
    private const EMPTY_SIDE = ['team' => 0, 'unused' => 0, 'consumed' => 0, 'awaiting' => 0, 'inactive' => 0, 'dummy' => 0];

    public function __construct(private readonly RuleVersionService $rules) {}

    /** @return Breakdown */
    public function forMember(Member $member): array
    {
        $sides = ['left' => self::EMPTY_SIDE, 'right' => self::EMPTY_SIDE];
        /** @var array<int, array{left: int, right: int}> $consumedByMilestone */
        $consumedByMilestone = [];

        $entries = DB::table('pair_entries')
            ->where('member_id', $member->id)
            ->selectRaw('side, status, consumed_for_milestone_no, count(*) as total')
            ->groupBy('side', 'status', 'consumed_for_milestone_no')
            ->get();

        foreach ($entries as $row) {
            $side = $row->side === 'left' ? 'left' : 'right';
            $count = (int) $row->total;

            if ($row->status === 'consumed') {
                $sides[$side]['consumed'] += $count;
                $milestoneNo = (int) $row->consumed_for_milestone_no;
                $consumedByMilestone[$milestoneNo] ??= ['left' => 0, 'right' => 0];
                $consumedByMilestone[$milestoneNo][$side] += $count;
            } else {
                $sides[$side]['unused'] += $count;
            }
        }

        // Every downline member that has NOT produced an entry for this member, bucketed by reason.
        $rows = DB::select(<<<'SQL'
            WITH RECURSIVE subtree AS (
                SELECT id, placement_side AS root_side
                FROM members
                WHERE placement_parent_id = ?
                UNION ALL
                SELECT m.id, s.root_side
                FROM members m
                INNER JOIN subtree s ON m.placement_parent_id = s.id
            )
            SELECT
                s.root_side,
                CASE
                    WHEN m.is_company_dummy = TRUE AND (m.dummy_status IS NULL OR m.dummy_status <> 'assigned') THEN 'dummy'
                    WHEN EXISTS (
                        SELECT 1 FROM pair_entries pe
                        INNER JOIN payments p ON p.id = pe.source_payment_id
                        WHERE pe.member_id = ? AND p.member_id = m.id
                    ) THEN 'eligible'
                    WHEN m.status <> 'active' THEN 'inactive'
                    ELSE 'awaiting'
                END AS bucket,
                m.membership_plan_id,
                COUNT(*) AS total
            FROM subtree s
            INNER JOIN members m ON m.id = s.id
            GROUP BY 1, 2, 3
            SQL, [$member->id, $member->id]);

        /** @var array<int, array{left: int, right: int}> $awaitingByPlanId */
        $awaitingByPlanId = [];

        foreach ($rows as $row) {
            if ($row->root_side !== 'left' && $row->root_side !== 'right') {
                continue;
            }

            $side = $row->root_side === 'left' ? 'left' : 'right';
            $count = (int) $row->total;
            $sides[$side]['team'] += $count;

            if ($row->bucket === 'awaiting') {
                $sides[$side]['awaiting'] += $count;
                $planId = (int) $row->membership_plan_id;
                $awaitingByPlanId[$planId] ??= ['left' => 0, 'right' => 0];
                $awaitingByPlanId[$planId][$side] += $count;
            } elseif ($row->bucket === 'inactive') {
                $sides[$side]['inactive'] += $count;
            } elseif ($row->bucket === 'dummy') {
                $sides[$side]['dummy'] += $count;
            }
        }

        ksort($consumedByMilestone);

        return [
            'left' => $sides['left'],
            'right' => $sides['right'],
            'consumed_by_milestone' => $consumedByMilestone,
            'awaiting_by_plan' => $this->awaitingByPlan($awaitingByPlanId),
        ];
    }

    /**
     * @param  array<int, array{left: int, right: int}>  $awaitingByPlanId
     * @return list<array{plan_name: string, required_emis: int|null, left: int, right: int}>
     */
    private function awaitingByPlan(array $awaitingByPlanId): array
    {
        if ($awaitingByPlanId === []) {
            return [];
        }

        /** @var array<string, int> $requiredEmis */
        $requiredEmis = $this->rules->value('pair_qualification_emis', []);

        return array_values(MembershipPlan::whereIn('id', array_keys($awaitingByPlanId))
            ->orderBy('code')
            ->get()
            ->map(fn (MembershipPlan $plan): array => [
                'plan_name' => $plan->name,
                'required_emis' => isset($requiredEmis[$plan->code]) ? (int) $requiredEmis[$plan->code] : null,
                'left' => $awaitingByPlanId[$plan->id]['left'],
                'right' => $awaitingByPlanId[$plan->id]['right'],
            ])
            ->all());
    }
}

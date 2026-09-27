<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Support\Facades\DB;

/**
 * T-129 (19-09-2026) — Super Admin's network overview: how big a member's
 * Binary Position downline is on each leg, how much of it is active /
 * inactive / unassigned-dummy, and which Store Owners sit inside it.
 *
 * Read-only, display-only — no compensation logic reads this. Leg totals use
 * the exact same subtree as `BinaryTeamSizeCounter::countSides()` (which
 * includes unassigned dummies, because Income Booster's thresholds do), so
 * this screen can never contradict the Booster's qualification maths; the
 * dummy share is reported separately instead of being hidden.
 *
 * Categories per leg: `total` = `active` + `inactive` + `dummy`, where
 * `dummy` = an unassigned company dummy entry (`is_company_dummy` and not
 * yet `dummy_status = assigned` — the same test `EvaluateBoosterQualification`
 * uses), `active` = a real member with status `active`, and `inactive` = any
 * other real member. A Store Owner is a member whose own user owns a store.
 *
 * One recursive SQL CTE for a whole batch of members (`forMany`) — never one
 * query per row — so a paginated list stays cheap however deep the tree is.
 *
 * @phpstan-type Leg array{total: int, active: int, inactive: int, dummy: int, store_owners: int}
 * @phpstan-type Summary array{left: Leg, right: Leg, team_total: int, store_owners: int}
 */
class MemberNetworkSummary
{
    private const EMPTY_LEG = ['total' => 0, 'active' => 0, 'inactive' => 0, 'dummy' => 0, 'store_owners' => 0];

    /**
     * @return Summary
     */
    public function forMember(Member $member): array
    {
        return $this->forMany([$member->id])[$member->id];
    }

    /**
     * @param  array<int, int>  $memberIds
     * @return array<int, Summary> keyed by member id; every requested id is present (zeros for a leaf).
     */
    public function forMany(array $memberIds): array
    {
        if ($memberIds === []) {
            return [];
        }

        /** @var array<int, Leg> $leftLegs */
        $leftLegs = [];
        /** @var array<int, Leg> $rightLegs */
        $rightLegs = [];

        $placeholders = implode(',', array_fill(0, count($memberIds), '?'));

        $rows = DB::select(<<<SQL
            WITH RECURSIVE subtree AS (
                SELECT id, placement_parent_id AS root_id, placement_side AS root_side
                FROM members
                WHERE placement_parent_id IN ({$placeholders})
                UNION ALL
                SELECT m.id, s.root_id, s.root_side
                FROM members m
                INNER JOIN subtree s ON m.placement_parent_id = s.id
            )
            SELECT
                s.root_id,
                s.root_side,
                COUNT(*) AS total,
                SUM(CASE WHEN m.is_company_dummy = TRUE AND (m.dummy_status IS NULL OR m.dummy_status <> 'assigned') THEN 1 ELSE 0 END) AS dummy,
                SUM(CASE WHEN NOT (m.is_company_dummy = TRUE AND (m.dummy_status IS NULL OR m.dummy_status <> 'assigned')) AND m.status = 'active' THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN EXISTS (SELECT 1 FROM stores st WHERE st.owner_user_id = m.user_id) THEN 1 ELSE 0 END) AS store_owners
            FROM subtree s
            INNER JOIN members m ON m.id = s.id
            GROUP BY s.root_id, s.root_side
            SQL, array_values($memberIds));

        foreach ($rows as $row) {
            $side = $row->root_side;

            if ($side !== 'left' && $side !== 'right') {
                continue;
            }

            $total = (int) $row->total;
            $dummy = (int) $row->dummy;
            $active = (int) $row->active;

            $leg = [
                'total' => $total,
                'active' => $active,
                'inactive' => $total - $dummy - $active,
                'dummy' => $dummy,
                'store_owners' => (int) $row->store_owners,
            ];

            if ($side === 'left') {
                $leftLegs[(int) $row->root_id] = $leg;
            } else {
                $rightLegs[(int) $row->root_id] = $leg;
            }
        }

        $result = [];

        foreach ($memberIds as $id) {
            $left = $leftLegs[$id] ?? self::EMPTY_LEG;
            $right = $rightLegs[$id] ?? self::EMPTY_LEG;

            $result[$id] = [
                'left' => $left,
                'right' => $right,
                'team_total' => $left['total'] + $right['total'],
                'store_owners' => $left['store_owners'] + $right['store_owners'],
            ];
        }

        return $result;
    }

    /**
     * The Store Owners inside a member's Binary Position downline, for the
     * Member Detail card (a short list — few members ever own a store).
     *
     * @return list<array{member_id: int, customer_id: string|null, name: string|null, store_name: string, side: string}>
     */
    public function storeOwnersIn(Member $member): array
    {
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
            SELECT m.id AS member_id, m.customer_id, u.name, st.name AS store_name, s.root_side
            FROM subtree s
            INNER JOIN members m ON m.id = s.id
            INNER JOIN users u ON u.id = m.user_id
            INNER JOIN stores st ON st.owner_user_id = m.user_id
            ORDER BY m.id
            SQL, [$member->id]);

        return array_values(array_map(fn ($row): array => [
            'member_id' => (int) $row->member_id,
            'customer_id' => $row->customer_id !== null ? (string) $row->customer_id : null,
            'name' => $row->name !== null ? (string) $row->name : null,
            'store_name' => (string) $row->store_name,
            'side' => (string) $row->root_side,
        ], $rows));
    }
}

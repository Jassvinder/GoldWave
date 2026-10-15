<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Support\Facades\DB;

/**
 * ARCHITECTURE.md: the ONE place `placement_parent_id` gets decided.
 * Implements the occupied-side traversal algorithm (DOMAIN_LOGIC.md §4.3):
 * start at the sponsor's selected Left/Right branch; if occupied, continue
 * straight down that same side (never alternating) until the first empty
 * slot, regardless of depth. Binary Position is fully independent of
 * Sponsor/Direct (DOMAIN_LOGIC.md §0) — this resolver only ever reads/writes
 * `placement_parent_id`/`placement_side`, never `sponsor_id`.
 */
class BinaryPlacementResolver
{
    /**
     * @param  'left'|'right'  $side
     * @return array{parent_id: int, side: string}
     */
    public function resolve(Member $sponsor, string $side): array
    {
        $current = $sponsor;

        while (true) {
            $occupant = Member::query()
                ->where('placement_parent_id', $current->id)
                ->where('placement_side', $side)
                ->first();

            if (! $occupant) {
                return ['parent_id' => $current->id, 'side' => $side];
            }

            $current = $occupant;
        }
    }

    /**
     * Every ancestor up $member's Binary Position chain, unbounded (no
     * per-level cap, unlike Level Income's 12 — DOMAIN_LOGIC.md §7's
     * Pair/Reward Beneficiary Chain Rule is a full team-size count all the
     * way to the tree root), each paired with which of THEIR two legs
     * (Left/Right) $member's subtree falls under. Index 0 = $member's
     * immediate placement parent, paired with $member's own placement_side.
     *
     * Fix 01-10-2026: the default used to be a 500-level cap, which silently
     * gave no Pair entry to any upline more than 500 levels above a joining
     * (GWL01's M4 on the dev DB). The walk is now unbounded by default; a
     * cycle in corrupted data is stopped by the visited-id guard instead.
     *
     * @return list<array{member: Member, side: string}>
     */
    public function ancestorsWithSide(Member $member, ?int $maxDepth = null): array
    {
        if ($member->placement_parent_id === null || $maxDepth === 0) {
            return [];
        }

        // 15-10-2026 — one recursive query instead of one query per level (a 553-level chain took ~1.5 s per walk,
        // and a cash approval walks it twice). Without a cycle a chain can't be longer than the member table, which
        // bounds the recursion; the visited-id guard below still stops at the first repeat, as before.
        $limit = min($maxDepth ?? PHP_INT_MAX, Member::count());

        $rows = DB::select(<<<'SQL'
            WITH RECURSIVE chain (id, parent_id, side, depth) AS (
                SELECT id, placement_parent_id, placement_side, 1 FROM members WHERE id = ?
                UNION ALL
                SELECT m.id, m.placement_parent_id, m.placement_side, c.depth + 1
                FROM members m
                INNER JOIN chain c ON m.id = c.parent_id
                WHERE c.depth < ?
            )
            SELECT id, side FROM chain ORDER BY depth
            SQL, [$member->placement_parent_id, $limit]);

        $ancestorIds = [];

        foreach ($rows as $row) {
            $ancestorIds[] = (int) $row->id;
        }

        $models = [];

        foreach (array_chunk($ancestorIds, 1000) as $ids) {
            foreach (Member::whereIn('id', $ids)->get() as $model) {
                $models[$model->id] = $model;
            }
        }

        $chain = [];
        $visited = [$member->id => true];
        // The side each ancestor is reached from: $member's own side first, then each child's side on the way up.
        $side = $member->placement_side;

        foreach ($rows as $row) {
            $id = (int) $row->id;

            if (isset($visited[$id]) || ! isset($models[$id])) {
                break;
            }

            $visited[$id] = true;
            $chain[] = ['member' => $models[$id], 'side' => $side];
            $side = $row->side;
        }

        return $chain;
    }

    /**
     * Whether $ancestor appears somewhere in $member's Binary Position chain
     * (or is $member itself). Used for Tree View authorization
     * (DOMAIN_LOGIC.md §4.2) — a member may navigate into their own
     * placement downline, never outside it. Capped at $maxDepth as a
     * corrupted-data trip-wire (same pattern as
     * SponsorChainResolver::isSelfOrDescendant).
     */
    public function isSelfOrDescendant(Member $ancestor, Member $member, int $maxDepth = 500): bool
    {
        $current = $member;

        for ($i = 0; $i < $maxDepth; $i++) {
            if ($current->id === $ancestor->id) {
                return true;
            }

            $parent = $current->placementParent()->first();

            if (! $parent) {
                return false;
            }

            $current = $parent;
        }

        return false;
    }
}

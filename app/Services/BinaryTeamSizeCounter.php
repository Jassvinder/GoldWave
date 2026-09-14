<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Support\Facades\DB;

/**
 * DOMAIN_LOGIC.md §9's implementation note: Income Booster's "Team size"
 * (with its Left/Right split) needs a live recompute of $ancestor's current
 * Binary Position subtree on each of their two legs — unlike Pair/Reward's
 * (§7) permanent consumable ledger, Booster just re-measures the current
 * count against a threshold each evaluation, with no "never reuse" concept.
 *
 * A single recursive SQL CTE per ancestor, rather than a per-node PHP walk —
 * Level 3's threshold alone is 3,000 team members, which an N+1 walk would
 * make prohibitively slow (DOMAIN_LOGIC.md §21 T-011 pre-coding pass).
 */
class BinaryTeamSizeCounter
{
    /** @return array{left: int, right: int} */
    public function countSides(Member $ancestor): array
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
            SELECT root_side, COUNT(*) AS cnt FROM subtree GROUP BY root_side
            SQL, [$ancestor->id]);

        $left = 0;
        $right = 0;

        foreach ($rows as $row) {
            if ($row->root_side === 'left') {
                $left = (int) $row->cnt;
            } elseif ($row->root_side === 'right') {
                $right = (int) $row->cnt;
            }
        }

        return ['left' => $left, 'right' => $right];
    }
}

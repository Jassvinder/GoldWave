<?php

namespace App\Services;

use App\Models\Member;

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
     * @return list<array{member: Member, side: string}>
     */
    public function ancestorsWithSide(Member $member, int $maxDepth = 500): array
    {
        $chain = [];
        $current = $member;

        for ($i = 0; $i < $maxDepth; $i++) {
            $parent = $current->placementParent()->first();

            if (! $parent) {
                break;
            }

            $chain[] = ['member' => $parent, 'side' => $current->placement_side];
            $current = $parent;
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

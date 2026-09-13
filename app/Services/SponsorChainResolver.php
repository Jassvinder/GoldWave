<?php

namespace App\Services;

use App\Models\Member;

/**
 * ARCHITECTURE.md: the ONE place Level Income (T-006), Purchase/Repurchase
 * Income (T-015 store module), and Store Profit Distribution resolve their
 * beneficiary chain from — walks `members.sponsor_id` upward, never
 * `placement_parent_id` (DOMAIN_LOGIC.md §0/§6's Level Income Chain Rule).
 *
 * Introduced in T-004 (its Sponsor/Direct-chain scope) even though no
 * compensation Action consumes it yet — those tasks (T-006+) depend on this
 * exact walk existing in one place rather than being reimplemented per
 * Action.
 */
class SponsorChainResolver
{
    /**
     * Ancestors 1..$maxLevels up the Sponsor/Direct chain, in order
     * (index 0 = the member's direct Sponsor, i.e. "Level 1"). Stops early
     * if the chain is shorter than $maxLevels — callers must handle a
     * shorter-than-expected result (DOMAIN_LOGIC.md §6.1 point 9: record a
     * skipped/non-payable reason rather than silently omitting a level).
     *
     * @return list<Member>
     */
    public function ancestors(Member $member, int $maxLevels = 12): array
    {
        $chain = [];
        $current = $member;

        for ($level = 0; $level < $maxLevels; $level++) {
            $sponsor = $current->sponsor()->first();

            if (! $sponsor) {
                break;
            }

            $chain[] = $sponsor;
            $current = $sponsor;
        }

        return $chain;
    }

    /**
     * Whether $ancestor appears somewhere in $member's Sponsor/Direct chain
     * (or is $member itself). Used for view-authorization (Directs View,
     * DOMAIN_LOGIC.md §4.1) — a member may navigate into their own downline,
     * never outside it. Capped at $maxDepth as a corrupted-data trip-wire,
     * the same defensive pattern used elsewhere in this project (see
     * DOMAIN_LOGIC.md §21's Purchase/Repurchase duplicate-beneficiary note).
     */
    public function isSelfOrDescendant(Member $ancestor, Member $member, int $maxDepth = 500): bool
    {
        $current = $member;

        for ($i = 0; $i < $maxDepth; $i++) {
            if ($current->id === $ancestor->id) {
                return true;
            }

            $sponsor = $current->sponsor()->first();

            if (! $sponsor) {
                return false;
            }

            $current = $sponsor;
        }

        return false;
    }
}

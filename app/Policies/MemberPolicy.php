<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\User;
use App\Services\BinaryPlacementResolver;
use App\Services\SponsorChainResolver;

/**
 * DOMAIN_LOGIC.md §4.1/§4.2: a member may view Directs View starting at
 * themselves and navigate into anyone in their own Sponsor/Direct downline
 * (never outside it, never via Binary Position) — DOMAIN_LOGIC.md §2's role
 * table: "Can view their own full downline tree." Super Admin can open
 * either view for any member (ARCHITECTURE.md Permissions table: "bypass
 * the ownership check explicitly, not by omitting the check").
 */
class MemberPolicy
{
    public function __construct(
        private readonly SponsorChainResolver $sponsorChainResolver,
        private readonly BinaryPlacementResolver $binaryPlacementResolver,
    ) {}

    public function viewDirects(User $user, Member $target): bool
    {
        if ($user->role === 'super_admin') {
            return true;
        }

        $viewer = $user->member;

        return $viewer !== null && $this->sponsorChainResolver->isSelfOrDescendant($viewer, $target);
    }

    public function viewTree(User $user, Member $target): bool
    {
        if ($user->role === 'super_admin') {
            return true;
        }

        $viewer = $user->member;

        return $viewer !== null && $this->binaryPlacementResolver->isSelfOrDescendant($viewer, $target);
    }
}

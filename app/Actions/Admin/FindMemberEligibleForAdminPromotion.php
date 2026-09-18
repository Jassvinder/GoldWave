<?php

namespace App\Actions\Admin;

use App\Models\Member;
use Illuminate\Validation\ValidationException;

/**
 * T-105 (17-09-2026) — Customer-ID lookup backing Admin Users' "find a Member
 * to promote" step, mirroring `Registration\ValidateSponsorCode`'s shape: an
 * unknown Customer ID and an ineligible one are treated identically, both
 * re-evaluated fresh on every call (never cached).
 */
class FindMemberEligibleForAdminPromotion
{
    public function __invoke(string $customerId): Member
    {
        $member = Member::where('customer_id', $customerId)->with('user')->first();

        if (! $member || $member->is_company_dummy || $member->status !== 'active' || ! $member->user || $member->user->role !== 'member') {
            throw ValidationException::withMessages([
                'customer_id' => 'No active Member found with that Customer ID who is eligible to become an Admin.',
            ]);
        }

        return $member;
    }
}

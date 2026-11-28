<?php

namespace App\Actions\Members;

use App\Models\Member;
use Illuminate\Database\Eloquent\Collection;

/**
 * T-251 part 3 (DOMAIN_LOGIC.md §3.4) — every ID of the member's person (same user), locked in id order, for the
 * actions that work on the whole person (Payout Hold, Account Block). A member without a user is only itself.
 */
final class PersonMembers
{
    /** @return Collection<int, Member> */
    public static function lock(Member $member): Collection
    {
        return Member::query()
            ->when(
                $member->user_id !== null,
                fn ($query) => $query->where('user_id', $member->user_id),
                fn ($query) => $query->whereKey($member->id),
            )
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }
}

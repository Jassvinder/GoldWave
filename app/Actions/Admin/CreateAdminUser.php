<?php

namespace App\Actions\Admin;

use App\Models\Member;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * INSTRUCTIONS.md S02 (Admin Users & Permissions), T-017 pre-coding pass
 * (`DOMAIN_LOGIC.md` §21) — promotes an existing, already-active Member's
 * *own* `users` row to `role=admin` (Store Owner). Fixed 17-09-2026, user-
 * reported: every real Store Owner is already a company Member first, so
 * this must never create a standalone `users` row disconnected from a
 * `members` row — doing so silently broke `Store::ownerMember()`
 * (`$this->owner?->member`, used by `CalculateStoreProfitDistribution`'s
 * owner-share crediting) for every admin previously created this way. No new
 * password is set — the Member already has one from registration (T-003).
 * Store assignment is a deliberately separate step: either `Actions\Store\
 * CreateStore` (new store + new owner together) or `Actions\Store\
 * ReassignStoreOwner` (an existing store's ownership).
 */
class CreateAdminUser
{
    public function __invoke(Member $member): User
    {
        $user = $member->user;

        if ($member->is_company_dummy || $member->status !== 'active' || ! $user || $user->role !== 'member') {
            throw ValidationException::withMessages([
                'customer_id' => 'Only an active, non-dummy Member who is not already an Admin or Super Admin can be promoted.',
            ]);
        }

        $user->update(['role' => 'admin']);

        return $user->fresh();
    }
}

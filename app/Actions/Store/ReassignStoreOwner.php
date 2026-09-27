<?php

namespace App\Actions\Store;

use App\Models\Store;
use App\Models\User;
use App\Services\StoreActivityLogger;

/**
 * INSTRUCTIONS.md S09 ("assign Store Owners"), T-017 pre-coding pass
 * (`DOMAIN_LOGIC.md` §21) — transfers an existing store's ownership to a
 * different `role=admin` user. Does not retroactively touch any
 * already-processed `StoreProfitDistribution` (its `beneficiary_member_id`
 * stays frozen to whoever owned the store when that sale was distributed,
 * matching this project's "never overwrite finalized history" principle);
 * only sales confirmed after the reassignment resolve to the new owner.
 *
 * T-117 (19-09-2026) — the Store Login password is always force-reset on
 * reassignment (user's own explicit call): the previous owner must not
 * retain working Admin/Store credentials once the store no longer belongs
 * to them. `$password` is the caller's already-decided plaintext
 * (auto-generated or manually typed) — this Action only hashes and stores
 * it, never decides which.
 */
class ReassignStoreOwner
{
    public function __construct(private readonly StoreActivityLogger $activityLog) {}

    public function __invoke(Store $store, User $newOwner, User $operator, string $password): Store
    {
        $oldOwnerUserId = $store->owner_user_id;

        $store->update(['owner_user_id' => $newOwner->id, 'password' => $password]);

        $this->activityLog->record(
            $store,
            $operator,
            'store_owner_reassigned',
            null,
            $store,
            ['owner_user_id' => $oldOwnerUserId],
            ['owner_user_id' => $newOwner->id],
        );

        return $store;
    }
}

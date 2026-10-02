<?php

namespace App\Actions\Store;

use App\Models\Store;
use App\Models\User;
use App\Services\StoreActivityLogger;
use Illuminate\Validation\ValidationException;

/**
 * T-117 (19-09-2026) — lets a Super Admin reset a store's Admin/Store Login
 * password at any time, independent of reassignment (which force-resets it
 * automatically, `ReassignStoreOwner`). `$password` is the caller's
 * already-decided plaintext (auto-generated or manually typed); this Action
 * only hashes and stores it.
 */
class ResetStorePassword
{
    public function __construct(private readonly StoreActivityLogger $activityLog) {}

    public function __invoke(Store $store, string $password, User $operator): Store
    {
        if ($store->owner_user_id === null) {
            throw ValidationException::withMessages([
                'password' => 'This store has no owner assigned yet — nothing to log in as.',
            ]);
        }

        $store->update(['password' => $password]);

        $this->activityLog->record($store, $operator, 'store_password_reset', null, $store);

        return $store;
    }
}

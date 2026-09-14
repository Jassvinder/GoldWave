<?php

namespace App\Actions\Admin;

use App\Models\User;

/**
 * INSTRUCTIONS.md S02 (Admin Users & Permissions), T-017 pre-coding pass
 * (`DOMAIN_LOGIC.md` §21) — creates a `role=admin` (Store Owner) account.
 * Store assignment is a deliberately separate step: either `Actions\Store\
 * CreateStore` (new store + new owner together) or `Actions\Store\
 * ReassignStoreOwner` (an existing store's ownership). Per §2's "Global Role
 * Rule," an Admin's only permission dimension in this app is which store (if
 * any) they own — there is no finer-grained access model to configure here.
 */
class CreateAdminUser
{
    public function __invoke(string $name, string $email, string $password): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => 'admin',
        ]);
    }
}

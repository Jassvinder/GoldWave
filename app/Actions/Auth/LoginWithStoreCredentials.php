<?php

namespace App\Actions\Auth;

use App\Models\Store;
use App\Models\User;
use App\Support\Portal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * T-117 (19-09-2026) — Admin/Store Login, the user's own explicit split
 * from the generic Fortify `/login`: an Admin (Store Owner) authenticates
 * with the store's own Store ID + Store password, never their email, and
 * never the Member Customer-ID login either — three fully separate login
 * paths for three fully separate identities/purposes. Same generic error
 * message for a wrong Store ID vs. a wrong password, matching
 * `LoginWithCustomerIdPassword`'s no-leak convention.
 */
class LoginWithStoreCredentials
{
    public function __invoke(string $storeCode, string $password): User
    {
        $store = Store::where('store_code', $storeCode)->first();

        if (! $store || $store->status !== 'active' || $store->password === null) {
            throw ValidationException::withMessages(['store_code' => 'Invalid Store ID or password.']);
        }

        if (! Hash::check($password, $store->password)) {
            throw ValidationException::withMessages(['store_code' => 'Invalid Store ID or password.']);
        }

        $owner = $store->owner;

        if ($owner === null) {
            throw ValidationException::withMessages(['store_code' => 'Invalid Store ID or password.']);
        }

        Auth::login($owner);
        session(['goldwave_login_method' => 'store_credentials']);
        Portal::stamp(Portal::STORE);

        return $owner;
    }
}

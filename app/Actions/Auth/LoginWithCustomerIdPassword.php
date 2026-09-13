<?php

namespace App\Actions\Auth;

use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §2.2 method 2 — User ID is the Customer ID; the initial
 * default password equals the Customer ID itself (set at activation, see
 * ActivateMembershipOnPaymentConfirmed). Same generic error message for a
 * wrong Customer ID vs. a wrong password, to avoid leaking which one failed.
 */
class LoginWithCustomerIdPassword
{
    public function __invoke(string $customerId, string $password): User
    {
        $member = Member::where('customer_id', $customerId)->first();

        if (! $member || $member->status !== 'active') {
            throw ValidationException::withMessages(['customer_id' => 'Invalid Customer ID or password.']);
        }

        $user = $member->loadMissing('user')->user;

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['customer_id' => 'Invalid Customer ID or password.']);
        }

        Auth::login($user);
        session(['goldwave_login_method' => 'password']);

        return $user;
    }
}

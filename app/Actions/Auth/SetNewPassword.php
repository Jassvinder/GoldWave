<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SetNewPassword
{
    public function __invoke(string $newPassword): User
    {
        $userId = session('password_reset_verified_user_id');
        $until = session('password_reset_verified_until');

        if (! $userId || ! $until || $until < now()->timestamp) {
            throw ValidationException::withMessages([
                'password' => 'OTP verification is required before changing your password.',
            ]);
        }

        $user = User::findOrFail((int) $userId);
        $user->update(['password' => $newPassword]);

        session()->forget(['password_reset_verified_user_id', 'password_reset_verified_until']);

        Auth::login($user);
        session(['goldwave_login_method' => 'otp']);

        return $user;
    }
}

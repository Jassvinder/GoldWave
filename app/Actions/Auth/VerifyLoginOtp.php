<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\OtpService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class VerifyLoginOtp
{
    public function __construct(private readonly OtpService $otp) {}

    public function __invoke(string $identifier, string $code): User
    {
        if (! $this->otp->verify($identifier, 'login', $code)) {
            throw ValidationException::withMessages(['otp' => 'Invalid or expired code.']);
        }

        $user = User::where('email', $identifier)->orWhere('mobile', $identifier)->first();

        if (! $user || $user->member?->status !== 'active') {
            throw ValidationException::withMessages(['otp' => 'No active GoldWave membership found.']);
        }

        Auth::login($user);
        session(['goldwave_login_method' => 'otp']);

        return $user;
    }
}

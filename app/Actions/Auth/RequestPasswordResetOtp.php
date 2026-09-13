<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\OtpService;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §2.2: "Password set/reset requires OTP first" — this is a
 * dedicated `purpose=password_reset` OTP, independent of a `login` OTP, so
 * completing a login never incidentally authorizes a password change.
 */
class RequestPasswordResetOtp
{
    public function __construct(private readonly OtpService $otp) {}

    public function __invoke(string $identifier): void
    {
        $user = User::where('email', $identifier)->orWhere('mobile', $identifier)->first();

        if (! $user || $user->member?->status !== 'active') {
            throw ValidationException::withMessages([
                'identifier' => 'No active GoldWave membership found for this mobile number or email.',
            ]);
        }

        $this->otp->request($identifier, 'password_reset');
    }
}

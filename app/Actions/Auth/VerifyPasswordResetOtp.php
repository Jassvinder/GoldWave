<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\OtpService;
use Illuminate\Validation\ValidationException;

/**
 * On success, marks the current session as OTP-verified-for-password-reset
 * for a short window — SetNewPassword refuses to run without this, which is
 * what makes OTP verification "the only gate for password changes"
 * (DOMAIN_LOGIC.md §2.2) structurally true rather than a UI-only guard.
 */
class VerifyPasswordResetOtp
{
    public function __construct(private readonly OtpService $otp) {}

    public function __invoke(string $identifier, string $code): void
    {
        if (! $this->otp->verify($identifier, 'password_reset', $code)) {
            throw ValidationException::withMessages(['otp' => 'Invalid or expired code.']);
        }

        $user = User::where('email', $identifier)->orWhere('mobile', $identifier)->firstOrFail();

        session([
            'password_reset_verified_user_id' => $user->id,
            'password_reset_verified_until' => now()->addMinutes(10)->timestamp,
        ]);
    }
}

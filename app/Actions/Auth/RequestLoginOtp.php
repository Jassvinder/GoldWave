<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\OtpService;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §2.2: OTP login is only possible for an already-Active
 * member — there is no "view my pending status" login. Works with either
 * the registered mobile or email, interchangeably.
 */
class RequestLoginOtp
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

        $this->otp->request($identifier, 'login');
    }
}

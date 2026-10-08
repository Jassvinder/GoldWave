<?php

namespace App\Actions\Registration;

use App\Models\User;
use App\Services\OtpService;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §12.2(d) — T-215. Checked before a Member's wallet-funded Assisted Registration creates anything:
 * no hold after an email/mobile change, and the code sent by `SendWalletPaymentOtp` is right, unexpired and unused.
 * A correct code is consumed, so it pays for exactly one registration.
 */
class VerifyWalletPaymentOtp
{
    public function __construct(private readonly OtpService $otp) {}

    public function __invoke(User $payer, string $code): void
    {
        SendWalletPaymentOtp::ensureNoHold($payer, 'payment_mode');

        if (! preg_match('/^\d{6}$/', $code)) {
            throw ValidationException::withMessages([
                'wallet_otp' => 'Enter the 6-digit code sent to your email.',
            ]);
        }

        if (! $this->otp->verify(SendWalletPaymentOtp::identifier($payer), 'wallet_payment', $code)) {
            throw ValidationException::withMessages([
                'wallet_otp' => 'This code is wrong or has expired. Check it, or send a new code.',
            ]);
        }
    }
}

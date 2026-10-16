<?php

namespace App\Actions\Registration;

use App\Actions\Payout\SubmitPayoutRequest;
use App\Contracts\SmsGatewayContract;
use App\Mail\WalletPaymentOtpMail;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §12.2(d) — T-215. Sends the 6-digit code a Member must enter before paying a new member's
 * registration from their own wallet. It always goes to the payer's registered email; with
 * `WALLET_OTP_SMS_ENABLED` it also goes by SMS to the registered mobile. The code is tied to the payer's account
 * (`user:<id>`), never to an address typed into the form.
 */
class SendWalletPaymentOtp
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly SmsGatewayContract $sms,
    ) {}

    /** @return string the masked email the code went to */
    public function __invoke(User $payer, ?string $newMemberName, ?string $planName): string
    {
        self::ensureNoHold($payer, 'wallet_otp');

        $identifier = self::identifier($payer);
        $wait = (int) config('otp.wallet_payment.resend_after_seconds');
        $lastIssuedAt = $this->otp->lastIssuedAt($identifier, 'wallet_payment');

        if ($lastIssuedAt !== null && $lastIssuedAt->copy()->addSeconds($wait)->isFuture()) {
            $seconds = (int) ceil(now()->diffInSeconds($lastIssuedAt->copy()->addSeconds($wait)));

            throw ValidationException::withMessages([
                'wallet_otp' => "Please wait {$seconds} seconds before asking for a new code.",
            ]);
        }

        $code = $this->otp->issue($identifier, 'email', 'wallet_payment');

        Mail::to($payer->email)->send(new WalletPaymentOtpMail($code, $newMemberName, $planName));

        if (config('otp.wallet_payment.sms_enabled') && $payer->mobile) {
            $this->sms->send(
                $payer->mobile,
                "Your GoldWave wallet payment code is {$code}. It approves paying a new member's registration from your wallet and is valid for 10 minutes. Do not share it with anyone.",
                'wallet_payment_otp',
            );
        }

        return self::maskEmail($payer->email);
    }

    public static function identifier(User $payer): string
    {
        return "user:{$payer->id}";
    }

    /** Refuses during the 48-hour hold that follows an email/mobile change. */
    public static function ensureNoHold(User $payer, string $errorKey): void
    {
        // T-231 (DOMAIN_LOGIC.md §2.3) — a Payout Hold or Account Block also stops wallet-paid registrations.
        if ($payer->member?->cannotTakeMoneyOut()) {
            throw ValidationException::withMessages([$errorKey => SubmitPayoutRequest::ON_HOLD_MESSAGE]);
        }

        $holdUntil = $payer->walletPaymentHoldUntil();

        if ($holdUntil !== null) {
            throw ValidationException::withMessages([
                $errorKey => self::holdMessage($holdUntil->format('d-m-Y h:i A')),
            ]);
        }
    }

    public static function holdMessage(string $until): string
    {
        return "Your email or mobile was changed recently. Wallet payments are available again after {$until}.";
    }

    /** `jassi@gmail.com` → `ja***@gmail.com`. */
    public static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 2).'***@'.$domain;
    }
}

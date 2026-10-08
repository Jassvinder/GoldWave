<?php

namespace App\Services;

use App\Contracts\OtpChannelContract;
use App\Models\OtpCode;
use App\Services\Otp\EmailOtpChannel;
use App\Services\Otp\SmsOtpChannel;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * The ONE place `otp_codes` rows get created and verified (DOMAIN_LOGIC.md
 * §2.2) — every Action that needs an OTP (login, password reset, wallet payment §12.2(d)) goes
 * through this service rather than querying `otp_codes` directly.
 *
 * Resolves its channel from the container by concrete class rather than
 * constructor-injecting both (which would require two same-typed
 * `OtpChannelContract` constructor params, making it impossible to bind a
 * different implementation per channel — e.g. a test double — via the
 * container).
 */
class OtpService
{
    public function __construct(private readonly Container $container) {}

    /**
     * @param  'login'|'password_reset'  $purpose
     */
    public function request(string $identifier, string $purpose): void
    {
        $channelType = $this->detectChannel($identifier);
        $code = $this->issue($identifier, $channelType, $purpose);

        $this->channelFor($channelType)->send($identifier, $code);
    }

    /**
     * Stores a new code and returns it unsent, for a caller that delivers it with its own message (T-215's wallet
     * payment code goes to email and, optionally, SMS with a payment-specific text).
     *
     * @param  'sms'|'email'  $channelType
     * @param  'login'|'password_reset'|'wallet_payment'  $purpose
     */
    public function issue(string $identifier, string $channelType, string $purpose): string
    {
        $code = (string) random_int(100000, 999999);

        OtpCode::create([
            'identifier' => $identifier,
            'channel' => $channelType,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);

        return $code;
    }

    /** When the newest code for this identifier and purpose was issued — used to space out resends. */
    public function lastIssuedAt(string $identifier, string $purpose): ?Carbon
    {
        $createdAt = OtpCode::where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->orderByDesc('id')
            ->value('created_at');

        return $createdAt ? Carbon::parse($createdAt) : null;
    }

    /**
     * @param  'login'|'password_reset'|'wallet_payment'  $purpose
     */
    public function verify(string $identifier, string $purpose, string $code): bool
    {
        $otp = OtpCode::where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->where('expires_at', '>=', now())
            ->orderByDesc('id')
            ->first();

        if (! $otp) {
            return false;
        }

        if ($otp->attempts >= 5) {
            return false;
        }

        $otp->increment('attempts');

        if (! Hash::check($code, $otp->code_hash)) {
            return false;
        }

        $otp->update(['consumed_at' => now()]);

        return true;
    }

    /** @return 'email'|'sms' */
    private function detectChannel(string $identifier): string
    {
        return filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'sms';
    }

    private function channelFor(string $channelType): OtpChannelContract
    {
        return $this->container->make($channelType === 'email' ? EmailOtpChannel::class : SmsOtpChannel::class);
    }
}

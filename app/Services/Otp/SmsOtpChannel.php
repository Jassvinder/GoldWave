<?php

namespace App\Services\Otp;

use App\Contracts\OtpChannelContract;
use App\Contracts\SmsGatewayContract;

/**
 * OTP by SMS (DOMAIN_LOGIC.md §2.2) — goes through the same `SmsGatewayContract` as every other SMS (T-139), so a real
 * provider is added in exactly one place. With the default `log` driver the code is only written to the log.
 */
class SmsOtpChannel implements OtpChannelContract
{
    public function __construct(private readonly SmsGatewayContract $sms) {}

    public function send(string $identifier, string $code): void
    {
        $this->sms->send($identifier, "Your GoldWave verification code is {$code}. It is valid for 10 minutes. Do not share it with anyone.", 'otp');
    }
}

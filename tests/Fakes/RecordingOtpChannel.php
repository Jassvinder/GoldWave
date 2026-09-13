<?php

namespace Tests\Fakes;

use App\Contracts\OtpChannelContract;

/**
 * Test double for both EmailOtpChannel and SmsOtpChannel — records the
 * plaintext code per identifier so a test can act as "the inbox" without
 * needing a real mail/SMS transport, the same way `Mail::fake()` would but
 * without pulling SMS delivery into that abstraction (no SMS vendor exists
 * to fake against yet — see SmsOtpChannel).
 */
class RecordingOtpChannel implements OtpChannelContract
{
    /** @var array<string, string> */
    public static array $sent = [];

    public function send(string $identifier, string $code): void
    {
        self::$sent[$identifier] = $code;
    }
}

<?php

namespace App\Services\Otp;

use App\Contracts\OtpChannelContract;
use Illuminate\Support\Facades\Log;

/**
 * No SMS gateway vendor has been chosen yet (a cost/vendor decision, same
 * category as the payment gateway in ARCHITECTURE.md) — this stub logs the
 * OTP so it stays usable for local dev/testing/manual QA. Swap this binding
 * in AppServiceProvider for a real SMS provider once one is selected; no
 * other code (OtpService, Auth Actions) changes.
 */
class SmsOtpChannel implements OtpChannelContract
{
    public function send(string $identifier, string $code): void
    {
        Log::info("[SMS OTP - no provider configured] to {$identifier}: {$code}");
    }
}

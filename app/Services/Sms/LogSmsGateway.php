<?php

namespace App\Services\Sms;

use App\Contracts\SmsGatewayContract;
use Illuminate\Support\Facades\Log;

/** Default SMS driver until a provider is chosen: nothing leaves the machine, the message is written to the log. */
class LogSmsGateway implements SmsGatewayContract
{
    public function send(string $mobile, string $message, string $templateKey): void
    {
        Log::info("[SMS:{$templateKey}] to {$mobile}: {$message}");
    }
}

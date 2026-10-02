<?php

namespace App\Contracts;

/**
 * T-139 — the one way the application sends an SMS (OTP, reminders, decisions). Implementations are chosen by
 * `SMS_DRIVER` in AppServiceProvider; the `log` driver only records the message until a real provider is selected
 * (India needs DLT-registered templates, so a real driver maps `$templateKey` to the provider's template id).
 */
interface SmsGatewayContract
{
    /**
     * @param  string  $mobile  the recipient's 10-digit mobile number
     * @param  string  $templateKey  a stable name for the message type (e.g. `otp`, `emi_due_reminder`)
     */
    public function send(string $mobile, string $message, string $templateKey): void;
}

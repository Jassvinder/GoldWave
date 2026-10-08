<?php

/*
| T-215 (08-10-2026) — DOMAIN_LOGIC.md §12.2(d): the one-time code a Member must enter to pay from their wallet.
|
| The code always goes to the payer's registered email. WALLET_OTP_SMS_ENABLED also sends it by SMS to the registered
| mobile; keep it false until an SMS pack is bought (with SMS_DRIVER=log the SMS would only be written to the log).
*/
return [
    'wallet_payment' => [
        'sms_enabled' => (bool) env('WALLET_OTP_SMS_ENABLED', false),
        'resend_after_seconds' => 60,
    ],

    // After Super Admin/Admin changes a member's email or mobile, wallet payments wait this long.
    'contact_change_hold_hours' => 48,
];

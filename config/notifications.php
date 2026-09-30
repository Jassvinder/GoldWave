<?php

/*
| T-139/T-140 — notification channels (DOMAIN_LOGIC.md §21 "Notifications, SMS and reminders").
|
| The in-app inbox (`database`: the header bell + the Notifications page) is always on. Email and SMS are extra channels,
| chosen per notification type below and switched off globally with NOTIFY_MAIL_ENABLED / NOTIFY_SMS_ENABLED. A recipient
| without an email address / mobile number simply skips that channel. SMS goes through `SmsGatewayContract`; SMS_DRIVER
| `log` only writes the message to the log until a real provider driver is added (provider not chosen yet).
*/
return [
    'mail_enabled' => (bool) env('NOTIFY_MAIL_ENABLED', true),
    'sms_enabled' => (bool) env('NOTIFY_SMS_ENABLED', true),
    'sms_driver' => env('SMS_DRIVER', 'log'),

    'channels' => [
        // Super Admin alerts — bell + page. Add 'mail' / 'sms' here to also get them by email / SMS.
        'cash_payment_awaiting_approval' => ['database'],
        'profile_change_request_submitted' => ['database'],
        'payout_request_submitted' => ['database'],
        'bank_details_submitted' => ['database'],
        // T-166 — the user asked for every channel: the Super Admin must act on it right away.
        'current_rate_booking_requested' => ['database', 'mail', 'sms'],
        // T-185a — like a Current Rate request, the metal is bought on approval.
        'store_emi_booking_requested' => ['database', 'mail', 'sms'],

        // Member notifications — things a member wants to know or must act on.
        'cash_payment_decided' => ['database', 'mail', 'sms'],
        'bank_details_verified' => ['database', 'mail', 'sms'],
        'assisted_registration_confirmed' => ['database', 'mail', 'sms'],
        'profile_change_request_reviewed' => ['database', 'mail', 'sms'],
        'emi_due_reminder' => ['database', 'mail', 'sms'],
        'current_rate_booking_decided' => ['database', 'mail', 'sms'],
        'store_emi_booking_decided' => ['database', 'mail', 'sms'],
        'store_emi_booking_broken' => ['database', 'mail', 'sms'],
    ],
];

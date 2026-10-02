<?php

namespace App\Notifications;

use App\Models\PayoutRequest;

/** Super Admin: a member requested a payout. */
class PayoutRequestSubmitted extends AppNotification
{
    public function __construct(public readonly PayoutRequest $payoutRequest) {}

    public function key(): string
    {
        return 'payout_request_submitted';
    }

    public function category(): string
    {
        return 'request';
    }

    public function title(): string
    {
        return 'Payout request';
    }

    public function body(): string
    {
        $member = $this->payoutRequest->member;
        $who = trim(($member->user->name ?? 'A member').($member->customer_id ? " ({$member->customer_id})" : ''));

        return "{$who} requested a payout of ₹".number_format((float) $this->payoutRequest->requested_amount, 2).'.';
    }

    public function url(): string
    {
        return '/super-admin/payout-requests';
    }
}

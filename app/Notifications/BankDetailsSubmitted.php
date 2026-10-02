<?php

namespace App\Notifications;

use App\Models\Member;

/** Super Admin: a member submitted bank details that need verifying before they can request a payout. */
class BankDetailsSubmitted extends AppNotification
{
    public function __construct(public readonly Member $member) {}

    public function key(): string
    {
        return 'bank_details_submitted';
    }

    public function category(): string
    {
        return 'request';
    }

    public function title(): string
    {
        return 'Bank details to verify';
    }

    public function body(): string
    {
        $who = trim(($this->member->user->name ?? 'A member').($this->member->customer_id ? " ({$this->member->customer_id})" : ''));

        return "{$who} submitted bank details. Verify them so the member can request payouts.";
    }

    public function url(): string
    {
        return '/super-admin/members/'.$this->member->id;
    }
}

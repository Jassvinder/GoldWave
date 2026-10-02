<?php

namespace App\Notifications;

use App\Models\StoreEmiBooking;

/** T-185a — Super Admin: a Store Admin asked for a member's Repurchase on EMI (DOMAIN_LOGIC.md §16.13). */
class StoreEmiBookingRequested extends AppNotification
{
    public function __construct(public readonly StoreEmiBooking $booking) {}

    public function key(): string
    {
        return 'store_emi_booking_requested';
    }

    public function category(): string
    {
        return 'request';
    }

    public function title(): string
    {
        return 'Repurchase on EMI request';
    }

    public function body(): string
    {
        $member = $this->booking->member;
        $who = trim(($member->user->name ?? 'A member').($member->customer_id ? " ({$member->customer_id})" : ''));

        return "{$who} — {$this->booking->item_name} ({$this->booking->weight_grams} g {$this->booking->metal}) over {$this->booking->installment_count} EMIs, at {$this->booking->store->name}. Approve to lock today's rate.";
    }

    public function url(): string
    {
        return '/super-admin/store-emi-bookings';
    }
}

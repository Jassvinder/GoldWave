<?php

namespace App\Notifications;

use App\Models\CurrentRateBookingRequest;

/**
 * T-166 (28-09-2026) — Super Admin: a member asked to book at the Current Rate. Sent immediately (notifications are
 * not queued) on every channel, so the metal can be bought right away.
 */
class CurrentRateBookingRequested extends AppNotification
{
    public function __construct(public readonly CurrentRateBookingRequest $bookingRequest) {}

    public function key(): string
    {
        return 'current_rate_booking_requested';
    }

    public function category(): string
    {
        return 'request';
    }

    public function title(): string
    {
        return 'Current Rate booking request';
    }

    public function body(): string
    {
        $member = $this->bookingRequest->member;
        $who = trim(($member->user->name ?? 'A member').($member->customer_id ? " ({$member->customer_id})" : ''));
        $estimate = $this->bookingRequest->estimate ?? [];
        $metal = isset($estimate['fixed_weight_grams'], $estimate['metal'])
            ? " — {$estimate['fixed_weight_grams']} g {$estimate['metal']}"
            : '';

        return "{$who} wants to book at the Current Rate{$metal}. Review and approve to lock today's rate.";
    }

    public function url(): string
    {
        return '/super-admin/rate-booking-requests';
    }
}

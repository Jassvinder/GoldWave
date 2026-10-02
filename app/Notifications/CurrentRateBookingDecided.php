<?php

namespace App\Notifications;

use App\Models\CurrentRateBookingRequest;

/** T-166 (28-09-2026) — Member: Super Admin approved or cancelled their Current Rate booking request. */
class CurrentRateBookingDecided extends AppNotification
{
    public function __construct(public readonly CurrentRateBookingRequest $bookingRequest) {}

    public function key(): string
    {
        return 'current_rate_booking_decided';
    }

    public function category(): string
    {
        return 'emi';
    }

    public function title(): string
    {
        return $this->bookingRequest->status === 'approved'
            ? 'Current Rate booking approved'
            : 'Current Rate booking cancelled';
    }

    public function body(): string
    {
        if ($this->bookingRequest->status === 'approved') {
            return 'Your plan is now booked at the Current Rate. Your remaining EMIs have been updated.';
        }

        $message = $this->bookingRequest->cancel_message;

        return 'Your Current Rate booking request was cancelled'.($message ? ": {$message}" : '.');
    }

    public function url(): string
    {
        return '/member/emi';
    }
}

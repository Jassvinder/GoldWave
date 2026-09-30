<?php

namespace App\Notifications;

use App\Models\StoreEmiBooking;

/** T-185a — the member and the requesting Store Admin: a Repurchase on EMI request was approved or cancelled. */
class StoreEmiBookingDecided extends AppNotification
{
    public function __construct(public readonly StoreEmiBooking $booking, public readonly bool $forMember) {}

    public function key(): string
    {
        return 'store_emi_booking_decided';
    }

    public function category(): string
    {
        return 'emi';
    }

    public function title(): string
    {
        return $this->booking->status === 'active'
            ? 'Repurchase on EMI approved'
            : 'Repurchase on EMI cancelled';
    }

    public function body(): string
    {
        $what = "{$this->booking->item_name} over {$this->booking->installment_count} EMIs";

        if ($this->booking->status === 'active') {
            return $this->forMember
                ? "Your {$what} is booked. EMI #1 is due today — pay it from your EMI Schedule page."
                : "The {$what} for {$this->booking->member->customer_id} is approved; the piece is now held for them.";
        }

        $message = $this->booking->cancel_message;

        return "The Repurchase on EMI request ({$what}) was cancelled".($message ? ": {$message}" : '.');
    }

    public function url(): string
    {
        return $this->forMember ? '/member/emi' : '/admin/store-emi';
    }
}

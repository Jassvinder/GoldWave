<?php

namespace App\Notifications;

use App\Models\StoreEmiBooking;

/** T-185b — the member and the requesting Store Admin: a Repurchase on EMI broke after 3 overdue EMIs (DOMAIN_LOGIC.md §16.13). */
class StoreEmiBookingBroken extends AppNotification
{
    public function __construct(public readonly StoreEmiBooking $booking, public readonly bool $forMember) {}

    public function key(): string
    {
        return 'store_emi_booking_broken';
    }

    public function category(): string
    {
        return 'emi';
    }

    public function title(): string
    {
        return 'Repurchase on EMI closed';
    }

    public function body(): string
    {
        $grams = (float) $this->booking->silver_grams_owed;
        $owed = $grams > 0
            ? "{$this->booking->silver_grams_owed} g of silver is owed for the amount paid — collect it at {$this->booking->store->name}."
            : 'No EMI was paid, so nothing is owed.';

        return $this->forMember
            ? "Your Repurchase on EMI of {$this->booking->item_name} was closed after 3 overdue EMIs. {$owed}"
            : "The Repurchase on EMI of {$this->booking->item_name} for {$this->booking->member->customer_id} was closed after 3 overdue EMIs; the piece is back in stock. {$owed}";
    }

    public function url(): string
    {
        return $this->forMember ? '/member/emi' : '/admin/store-emi';
    }
}

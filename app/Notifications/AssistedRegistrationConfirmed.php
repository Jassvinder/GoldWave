<?php

namespace App\Notifications;

use App\Models\Payment;

/**
 * DOMAIN_LOGIC.md §12.2(b) — T-153. The new member, once their Assisted
 * Registration is settled from someone else's wallet — deliberately
 * separate from `CashPaymentDecided` (that notification's wording is
 * specific to cash, which this was never funded by).
 */
class AssistedRegistrationConfirmed extends AppNotification
{
    public function __construct(public readonly Payment $payment) {}

    public function key(): string
    {
        return 'assisted_registration_confirmed';
    }

    public function category(): string
    {
        return 'payment';
    }

    public function title(): string
    {
        return 'Registration confirmed';
    }

    public function body(): string
    {
        $amount = '₹'.number_format((float) $this->payment->amount, 2);

        return "Your registration ({$amount}) has been confirmed — Customer ID {$this->payment->member->customer_id}.";
    }
}

<?php

namespace App\Notifications;

use App\Models\Payment;

/**
 * T-228/T-229 — a payment settled in the background (approval, store cash collection, wallet-funded registration,
 * online confirmation) failed. Nothing was kept, so the payment is still pending. Sent to the company users, and to
 * the store admin / member who started it.
 */
class PaymentSettlementFailed extends AppNotification
{
    /**
     * @param  string  $what  e.g. "Payment approval", shown as "Payment approval failed"
     * @param  string|null  $link  where the recipient can act on it (only a page they can open)
     */
    public function __construct(
        public readonly Payment $payment,
        public readonly string $what,
        public readonly string $advice,
        public readonly ?string $link = null,
    ) {}

    public function key(): string
    {
        return 'payment_settlement_failed';
    }

    public function category(): string
    {
        return 'payment';
    }

    public function title(): string
    {
        return "{$this->what} failed";
    }

    public function body(): string
    {
        $member = $this->payment->member;
        $who = trim(($member->placeholder_name ?? $member->user->name ?? 'A member').($member->customer_id ? " ({$member->customer_id})" : ''));
        $amount = '₹'.number_format((float) $this->payment->amount, 2);

        return "{$this->what} for {$who}'s payment of {$amount} could not be completed. Nothing was changed. {$this->advice}";
    }

    public function url(): ?string
    {
        return $this->link;
    }
}

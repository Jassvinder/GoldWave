<?php

namespace App\Notifications;

use App\Models\Payment;

/** Member: their cash payment was approved or rejected by the Super Admin. */
class CashPaymentDecided extends AppNotification
{
    public function __construct(public readonly Payment $payment, public readonly bool $approved) {}

    public function key(): string
    {
        return 'cash_payment_decided';
    }

    public function category(): string
    {
        return 'payment';
    }

    public function title(): string
    {
        return $this->approved ? 'Cash payment approved' : 'Cash payment rejected';
    }

    public function body(): string
    {
        // T-251 — a combined payment for several IDs: one message with the total and every Customer ID.
        $batch = $this->payment->batch;

        if ($batch !== null) {
            $total = '₹'.number_format((float) $batch->amount, 2);
            $rows = $batch->payments()->with('member')->get();
            $count = $rows->count();

            if (! $this->approved) {
                return "Your payment of {$total} for {$count} IDs was not accepted. Please contact GoldWave to sort it out.";
            }

            $ids = $rows->map(fn (Payment $row) => $row->member?->customer_id)->filter()->implode(', ');

            return $batch->type === 'registration'
                ? "Your payment of {$total} for {$count} IDs was approved. Your IDs are active: {$ids}. Log in with any of them."
                : "Your payment of {$total} for the EMIs of {$count} IDs was approved ({$ids}).";
        }

        $amount = '₹'.number_format((float) $this->payment->amount, 2);
        $for = $this->payment->type === 'registration'
            ? 'your registration'
            : $this->payment->emiLabel();

        if (! $this->approved) {
            return "Your cash payment of {$amount} for {$for} was not accepted. Please contact GoldWave to sort it out.";
        }

        $customerId = $this->payment->type === 'registration' ? $this->payment->member->customer_id : null;

        return "Your cash payment of {$amount} for {$for} was approved."
            .($customerId ? " Your membership is active — your Customer ID is {$customerId}." : '');
    }

    public function url(): ?string
    {
        return $this->payment->type === 'registration' ? '/member/login' : '/member/emi';
    }
}

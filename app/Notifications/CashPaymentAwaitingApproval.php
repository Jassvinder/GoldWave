<?php

namespace App\Notifications;

use App\Models\Payment;

/** Super Admin: a member's cash payment (registration or EMI installment) is waiting for approval. */
class CashPaymentAwaitingApproval extends AppNotification
{
    public function __construct(public readonly Payment $payment) {}

    public function key(): string
    {
        return 'cash_payment_awaiting_approval';
    }

    public function category(): string
    {
        return 'payment';
    }

    public function title(): string
    {
        return $this->payment->mode === 'upi' ? 'GPay/UPI payment awaiting approval' : 'Cash payment awaiting approval';
    }

    public function body(): string
    {
        $member = $this->payment->member;
        $who = trim(($member->user->name ?? 'A member').($member->customer_id ? " ({$member->customer_id})" : ''));
        $amount = '₹'.number_format((float) $this->payment->amount, 2);
        $for = $this->payment->type === 'registration'
            ? 'a new registration'
            : $this->payment->emiLabel();

        $how = $this->payment->mode === 'upi' ? 'a GPay/UPI' : 'a cash';

        return "{$who} submitted {$how} payment of {$amount} for {$for}.";
    }

    public function url(): string
    {
        return '/super-admin/cash-payments';
    }
}

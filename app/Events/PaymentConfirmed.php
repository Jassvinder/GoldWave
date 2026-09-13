<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ARCHITECTURE.md's event-driven compensation wiring: fired once a payment
 * (registration or, later, EMI installment) is confirmed via either the
 * online webhook or Super Admin cash approval. Level Income (T-006), Pair
 * Entry creation (T-007), and EMI status updates (T-005) all listen for this
 * rather than being called inline from the payment-confirmation Actions —
 * no listeners exist yet for those tasks; this event is the wiring point
 * they'll attach to without touching Payment/Registration code.
 */
class PaymentConfirmed
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Payment $payment) {}
}

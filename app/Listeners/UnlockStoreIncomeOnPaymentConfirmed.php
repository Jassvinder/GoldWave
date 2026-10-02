<?php

namespace App\Listeners;

use App\Events\PaymentConfirmed;
use App\Services\StoreIncomeUnlock;

/**
 * T-183 (DOMAIN_LOGIC.md §15) — a confirmed payment is what makes a direct qualified (a one-time plan's registration,
 * or the EMI that reaches the plan's qualification count), so the payer's sponsor is checked right then: reaching 10
 * qualified directs unlocks their store upline income for life, even if a direct is lost before any store sale.
 */
class UnlockStoreIncomeOnPaymentConfirmed
{
    public function __construct(private readonly StoreIncomeUnlock $unlock) {}

    public function handle(PaymentConfirmed $event): void
    {
        $sponsor = $event->payment->member?->sponsor;

        if ($sponsor !== null) {
            $this->unlock->isUnlocked($sponsor);
        }
    }
}

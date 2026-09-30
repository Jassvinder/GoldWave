<?php

namespace App\Listeners;

use App\Actions\Compensation\ReleaseHeldLevelIncome;
use App\Events\PaymentConfirmed;

/**
 * T-186 (DOMAIN_LOGIC.md §6) — a confirmed payment is what makes a direct qualified (a one-time plan's registration,
 * or the EMI that reaches the plan's qualification count), so the payer's sponsor is checked right then and any Level
 * Income held for too few directs is released for the levels now met.
 */
class ReleaseHeldLevelIncomeOnPaymentConfirmed
{
    public function __construct(private readonly ReleaseHeldLevelIncome $release) {}

    public function handle(PaymentConfirmed $event): void
    {
        $sponsor = $event->payment->member?->sponsor;

        if ($sponsor !== null) {
            ($this->release)($sponsor);
        }
    }
}

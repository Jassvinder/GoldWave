<?php

namespace App\Listeners;

use App\Actions\Compensation\CalculateLevelIncome;
use App\Events\PaymentConfirmed;

/**
 * ARCHITECTURE.md's event-driven compensation wiring (T-006): every
 * confirmed payment — registration or EMI installment alike — triggers Level
 * Income independently (DOMAIN_LOGIC.md §6.1, Docs/TEST.md scenario 8), with
 * no EMI-specific branching needed here since `PaymentConfirmed` already
 * carries the right eligible amount for either payment type.
 */
class CalculateLevelIncomeOnPaymentConfirmed
{
    public function __construct(private readonly CalculateLevelIncome $calculateLevelIncome) {}

    public function handle(PaymentConfirmed $event): void
    {
        ($this->calculateLevelIncome)($event->payment);
    }
}

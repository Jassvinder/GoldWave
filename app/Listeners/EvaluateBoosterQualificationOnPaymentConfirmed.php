<?php

namespace App\Listeners;

use App\Actions\Compensation\EvaluateBoosterQualification;
use App\Events\PaymentConfirmed;

/**
 * ARCHITECTURE.md's event-driven compensation wiring (T-011) — the third
 * listener on `PaymentConfirmed` alongside T-006's/T-007's. The Action
 * itself filters to `registration` payments only (Booster's structural
 * inputs never change on a later EMI installment), so no branching is
 * needed here.
 */
class EvaluateBoosterQualificationOnPaymentConfirmed
{
    public function __construct(private readonly EvaluateBoosterQualification $evaluateBoosterQualification) {}

    public function handle(PaymentConfirmed $event): void
    {
        ($this->evaluateBoosterQualification)($event->payment);
    }
}

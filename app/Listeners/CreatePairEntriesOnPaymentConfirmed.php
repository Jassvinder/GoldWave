<?php

namespace App\Listeners;

use App\Actions\Compensation\CreatePairEntries;
use App\Events\PaymentConfirmed;

/**
 * ARCHITECTURE.md's event-driven compensation wiring (T-007) — the second
 * listener on `PaymentConfirmed` alongside T-006's
 * `CalculateLevelIncomeOnPaymentConfirmed`. Laravel dispatches every
 * registered listener for an event, so both fire independently off the same
 * confirmed payment with no coupling between them.
 */
class CreatePairEntriesOnPaymentConfirmed
{
    public function __construct(private readonly CreatePairEntries $createPairEntries) {}

    public function handle(PaymentConfirmed $event): void
    {
        ($this->createPairEntries)($event->payment);
    }
}

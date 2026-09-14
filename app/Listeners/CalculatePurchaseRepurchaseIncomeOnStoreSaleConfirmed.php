<?php

namespace App\Listeners;

use App\Actions\Compensation\CalculatePurchaseRepurchaseIncome;
use App\Events\StoreSaleConfirmed;

/**
 * ARCHITECTURE.md's event-driven compensation wiring: every confirmed store
 * sale triggers Purchase/Repurchase Upline Income independently of Store
 * Profit Distribution (DOMAIN_LOGIC.md §15) — the Action itself is a no-op
 * when the sale has no purchasing member.
 */
class CalculatePurchaseRepurchaseIncomeOnStoreSaleConfirmed
{
    public function __construct(private readonly CalculatePurchaseRepurchaseIncome $calculate) {}

    public function handle(StoreSaleConfirmed $event): void
    {
        ($this->calculate)($event->storeSale);
    }
}

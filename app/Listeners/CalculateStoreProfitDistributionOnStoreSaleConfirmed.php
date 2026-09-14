<?php

namespace App\Listeners;

use App\Actions\Compensation\CalculateStoreProfitDistribution;
use App\Events\StoreSaleConfirmed;

/**
 * ARCHITECTURE.md's event-driven compensation wiring: every confirmed store
 * sale triggers Store Profit Distribution independently of Purchase/
 * Repurchase Upline Income (DOMAIN_LOGIC.md §16.4).
 */
class CalculateStoreProfitDistributionOnStoreSaleConfirmed
{
    public function __construct(private readonly CalculateStoreProfitDistribution $calculate) {}

    public function handle(StoreSaleConfirmed $event): void
    {
        ($this->calculate)($event->storeSale);
    }
}

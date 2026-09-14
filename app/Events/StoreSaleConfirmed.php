<?php

namespace App\Events;

use App\Models\StoreSale;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ARCHITECTURE.md's event-driven compensation wiring: fired once a
 * `store_sales` row is confirmed. Store Profit Distribution
 * (`CalculateStoreProfitDistribution`) always listens; Purchase/Repurchase
 * Upline Income (`CalculatePurchaseRepurchaseIncome`) listens too but is a
 * no-op when the sale has no `member_id` (DOMAIN_LOGIC.md §16.4's walk-in/
 * non-member scenario). Never fired for a `StoreBuyback` — that is the
 * reverse of a sale and is outside both rules' scope (§16.9).
 */
class StoreSaleConfirmed
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly StoreSale $storeSale) {}
}

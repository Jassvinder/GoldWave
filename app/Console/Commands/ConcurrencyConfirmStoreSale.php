<?php

namespace App\Console\Commands;

use App\Actions\Store\ConfirmStoreSale;
use App\Models\Store;
use App\Models\StoreInventoryItem;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * T-020 (DOMAIN_LOGIC.md §21) — a single-purpose command launched as a real,
 * separate OS process by tests/Concurrency/StoreInventoryConcurrencyTest.php
 * to produce a genuine race against ConfirmStoreSale's lockForUpdate()
 * stock-check critical section (§16.8). Never registered as user-facing
 * functionality — exists purely as a concurrency-test worker.
 */
class ConcurrencyConfirmStoreSale extends Command
{
    protected $signature = 'concurrency:confirm-store-sale {storeId} {inventoryItemId} {quantity} {operatorId}';

    protected $description = 'Test worker: attempt one walk-in store sale against tracked inventory, for real-process concurrency testing.';

    public function handle(ConfirmStoreSale $action): int
    {
        $store = Store::findOrFail((int) $this->argument('storeId'));
        $item = StoreInventoryItem::findOrFail((int) $this->argument('inventoryItemId'));
        $operator = User::findOrFail((int) $this->argument('operatorId'));
        $quantity = (int) $this->argument('quantity');
        $rate = (float) $item->price;

        try {
            $action(
                $store,
                null,
                'new_sale',
                $item->item_name,
                $item,
                (float) $item->weight,
                $quantity,
                $rate,
                $rate * $quantity,
                0,
                'cash',
                $operator,
            );
            $this->line('SUCCESS');

            return self::SUCCESS;
        } catch (ValidationException) {
            $this->line('BLOCKED');

            return self::FAILURE;
        }
    }
}

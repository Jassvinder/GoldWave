<?php

namespace App\Actions\Store;

use App\Models\Store;
use App\Models\StoreInventoryItem;
use App\Models\User;
use App\Services\StoreActivityLogger;

/**
 * DOMAIN_LOGIC.md §16.5 — Super Admin's jewellery allocation to a store's
 * item-wise inventory. A second allocation of the same item name/metal/
 * weight/price to the same store adds to the existing row's quantity rather
 * than creating a duplicate row for what is really the same stock item.
 */
class AllocateStoreInventoryItem
{
    public function __construct(private readonly StoreActivityLogger $activityLog) {}

    public function __invoke(
        Store $store,
        string $itemName,
        string $metal,
        float $weight,
        int $quantity,
        float $price,
        User $operator,
        ?string $description = null,
    ): StoreInventoryItem {
        $existing = $store->inventoryItems()
            ->where('item_name', $itemName)
            ->where('metal', $metal)
            ->where('weight', $weight)
            ->where('price', $price)
            ->first();

        if ($existing) {
            $existing->increment('quantity', $quantity);
            $item = $existing->fresh();
        } else {
            $item = $store->inventoryItems()->create([
                'item_name' => $itemName,
                'metal' => $metal,
                'weight' => $weight,
                'quantity' => $quantity,
                'price' => $price,
                'description' => $description,
            ]);
        }

        $this->activityLog->record($store, $operator, 'inventory_allocated', null, $item, null, ['quantity_added' => $quantity]);

        return $item;
    }
}

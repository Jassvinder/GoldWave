<?php

namespace App\Actions\Store;

use App\Models\StoreRestockShipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §16.12 — T-152. The store (or Super Admin on its behalf)
 * confirms a sent restock shipment has arrived — the point the item
 * actually lands in the store's tracked inventory. Reuses
 * `AllocateStoreInventoryItem`'s existing merge-or-create logic rather than
 * duplicating it, so a restocked item is indistinguishable from any other
 * allocated stock once received.
 */
class MarkRestockShipmentReceived
{
    public function __construct(private readonly AllocateStoreInventoryItem $allocateInventory) {}

    public function __invoke(StoreRestockShipment $shipment, User $operator): StoreRestockShipment
    {
        return DB::transaction(function () use ($shipment, $operator) {
            $locked = StoreRestockShipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'sent') {
                throw ValidationException::withMessages([
                    'shipment' => 'Only a shipment already marked sent can be confirmed received.',
                ]);
            }

            $item = ($this->allocateInventory)(
                $locked->store()->firstOrFail(),
                $locked->item_name,
                $locked->metal,
                (float) ($locked->weight ?? 0),
                1,
                (float) $locked->value,
                $operator,
                'Restock shipment #'.$locked->id,
            );

            $locked->update([
                'status' => 'received',
                'received_at' => now(),
                'received_by' => $operator->id,
                'resulting_inventory_item_id' => $item->id,
            ]);

            return $locked->fresh();
        });
    }
}

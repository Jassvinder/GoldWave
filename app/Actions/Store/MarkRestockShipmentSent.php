<?php

namespace App\Actions\Store;

use App\Models\StoreRestockShipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §16.12 — T-152. Super Admin marks a restock shipment as
 * physically sent to the store. Company-side only, mirroring the rest of
 * this project's "jewellery allocation belongs to Super Admin" boundary
 * (§16.1/§16.5) — a store cannot mark its own restock sent, only received.
 */
class MarkRestockShipmentSent
{
    public function __invoke(StoreRestockShipment $shipment, User $operator): StoreRestockShipment
    {
        return DB::transaction(function () use ($shipment, $operator) {
            $locked = StoreRestockShipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'owed') {
                throw ValidationException::withMessages([
                    'shipment' => 'Only a shipment still owed can be marked sent.',
                ]);
            }

            $locked->update([
                'status' => 'sent',
                'sent_at' => now(),
                'sent_by' => $operator->id,
            ]);

            return $locked->fresh();
        });
    }
}

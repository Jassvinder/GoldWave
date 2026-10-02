<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Store\MarkRestockShipmentReceived;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreBuyback;
use App\Models\StoreInventoryItem;
use App\Models\StoreRestockShipment;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * INSTRUCTIONS.md A04 — products, stock, stock movements, inventory status.
 * Read-only for Admin/Store Owner: jewellery *allocation* itself is a Super
 * Admin action (DOMAIN_LOGIC.md §16.1); Item Buyback (the one way this
 * store's own stock grows without Super Admin) is recorded from A03
 * alongside sales/repurchases, not here — this page shows both the current
 * stock levels and the buyback history that fed them, as the "stock
 * movements" the page title calls for.
 */
class InventoryController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        $items = $store->inventoryItems()
            ->orderBy('item_name')
            ->get()
            ->map(fn (StoreInventoryItem $item): array => [
                'id' => $item->id,
                'item_name' => $item->item_name,
                'metal' => $item->metal,
                'weight' => $item->weight,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'description' => $item->description,
            ]);

        $buybacks = StoreBuyback::where('store_id', $store->id)
            ->with('member')
            ->orderByDesc('id')
            ->get()
            ->map(fn (StoreBuyback $buyback): array => [
                'item_name' => $buyback->item_name,
                'metal' => $buyback->metal,
                'weight' => $buyback->weight,
                'quantity' => $buyback->quantity,
                'price_paid' => $buyback->price_paid,
                'customer_id' => $buyback->member?->customer_id,
                'occurred_at' => Dates::date($buyback->occurred_at),
            ]);

        $pendingRestocks = StoreRestockShipment::where('store_id', $store->id)
            ->whereIn('status', ['owed', 'sent'])
            ->orderBy('id')
            ->get()
            ->map(fn (StoreRestockShipment $shipment): array => [
                'id' => $shipment->id,
                'item_name' => $shipment->item_name,
                'metal' => $shipment->metal,
                'weight' => $shipment->weight,
                'value' => $shipment->value,
                'status' => $shipment->status,
            ]);

        return Inertia::render('admin/inventory', [
            'items' => $items,
            'buybacks' => $buybacks,
            'pending_restocks' => $pendingRestocks,
        ]);
    }

    /** DOMAIN_LOGIC.md §16.12 — T-152. The store confirms a sent restock shipment has arrived. */
    public function markRestockReceived(StoreRestockShipment $shipment, Request $request, MarkRestockShipmentReceived $action): RedirectResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        if ($shipment->store_id !== $store->id) {
            abort(403);
        }

        $action($shipment, $request->user());

        return redirect()->route('admin.inventory.index')->with('status', 'Restock confirmed received and added to your inventory.');
    }
}

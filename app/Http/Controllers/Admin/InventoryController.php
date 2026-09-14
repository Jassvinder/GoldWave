<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreBuyback;
use App\Models\StoreInventoryItem;
use App\Support\Dates;
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

        return Inertia::render('admin/inventory', [
            'items' => $items,
            'buybacks' => $buybacks,
        ]);
    }
}

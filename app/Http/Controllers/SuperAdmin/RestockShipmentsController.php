<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Store\MarkRestockShipmentSent;
use App\Http\Controllers\Controller;
use App\Models\StoreRestockShipment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DOMAIN_LOGIC.md §16.12 — T-152. Super Admin's view of every restock owed
 * to a store, and the "mark sent" action (company-side only, mirroring the
 * rest of this project's jewellery-allocation boundary).
 */
class RestockShipmentsController extends Controller
{
    public function index(): Response
    {
        $shipments = StoreRestockShipment::with('store')
            ->orderByRaw("CASE status WHEN 'owed' THEN 0 WHEN 'sent' THEN 1 ELSE 2 END")
            ->orderByDesc('id')
            ->get()
            ->map(fn (StoreRestockShipment $shipment): array => [
                'id' => $shipment->id,
                'store_name' => $shipment->store?->name,
                'item_name' => $shipment->item_name,
                'metal' => $shipment->metal,
                'weight' => $shipment->weight,
                'value' => $shipment->value,
                'status' => $shipment->status,
                'sent_at' => $shipment->sent_at,
                'received_at' => $shipment->received_at,
                'created_at' => $shipment->created_at,
            ]);

        return Inertia::render('super-admin/restock-shipments', [
            'shipments' => $shipments,
        ]);
    }

    public function markSent(StoreRestockShipment $shipment, Request $request, MarkRestockShipmentSent $action): RedirectResponse
    {
        $action($shipment, $request->user());

        return redirect()->route('super-admin.restock-shipments.index')->with('status', 'Shipment marked sent.');
    }
}

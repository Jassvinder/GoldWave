<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Store\ConfirmStoreSale;
use App\Actions\Store\RecordItemBuyback;
use App\Actions\Store\RecordPlanJewelleryDelivery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RecordItemBuybackRequest;
use App\Http\Requests\Admin\RecordPlanJewelleryDeliveryRequest;
use App\Http\Requests\Admin\RecordStoreSaleRequest;
use App\Models\Member;
use App\Models\MetalRate;
use App\Models\Store;
use App\Models\StoreInventoryItem;
use App\Models\StoreSale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * INSTRUCTIONS.md A03 — "Manage new joining payments and store repurchases/
 * sales; use Store Wallet as payment source; generate invoices; view
 * transaction status." Reuses T-014's Actions untouched: `ConfirmStoreSale`
 * for New Sale/Purchase/Repurchase (Store Wallet is already one of its
 * `paymentSource` options), `RecordItemBuyback` for the reverse-direction
 * transaction (DOMAIN_LOGIC.md §16.7), and `RecordPlanJewelleryDelivery` for
 * "new joining payments" — a newly-activated member's plan-jewellery
 * entitlement handed over at this store (DOMAIN_LOGIC.md §16.10).
 */
class SalesController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        $inventoryItems = $store->inventoryItems()
            ->where('quantity', '>', 0)
            ->orderBy('item_name')
            ->get()
            ->map(fn (StoreInventoryItem $item): array => [
                'id' => $item->id,
                'item_name' => $item->item_name,
                'metal' => $item->metal,
                'weight' => $item->weight,
                'quantity' => $item->quantity,
                'price' => $item->price,
            ]);

        $recentSales = StoreSale::where('store_id', $store->id)
            ->with(['member', 'invoice'])
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (StoreSale $sale): array => [
                'id' => $sale->id,
                'transaction_type' => $sale->transaction_type,
                'customer_id' => $sale->member?->customer_id,
                'item_name' => $sale->item_name,
                'total_invoice_amount' => $sale->total_invoice_amount,
                'payment_source' => $sale->payment_source,
                'distribution_status' => $sale->distribution_status,
                'invoice_no' => $sale->invoice?->invoice_no,
            ]);

        return Inertia::render('admin/sales', [
            'inventory_items' => $inventoryItems,
            'recent_sales' => $recentSales,
        ]);
    }

    public function storeSale(RecordStoreSaleRequest $request, ConfirmStoreSale $action): RedirectResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        $member = $this->resolveMember($request->string('customer_id')->toString() ?: null);

        $inventoryItem = null;

        if ($request->filled('store_inventory_item_id')) {
            $inventoryItem = StoreInventoryItem::where('store_id', $store->id)->findOrFail($request->integer('store_inventory_item_id'));
        }

        $itemName = $inventoryItem !== null ? $inventoryItem->item_name : $request->string('item_name')->toString();

        $action(
            store: $store,
            member: $member,
            transactionType: $request->string('transaction_type')->toString(),
            itemName: $itemName,
            inventoryItem: $inventoryItem,
            itemWeight: $request->filled('item_weight') ? (float) $request->input('item_weight') : $inventoryItem?->weight,
            quantity: $request->integer('quantity'),
            rate: $request->filled('rate') ? (float) $request->input('rate') : null,
            saleAmount: (float) $request->input('sale_amount'),
            gstAmount: (float) ($request->input('gst_amount') ?? 0),
            paymentSource: $request->string('payment_source')->toString(),
            operator: $request->user(),
        );

        return redirect()->route('admin.sales.index')->with('status', 'Sale recorded and invoice generated.');
    }

    public function storeBuyback(RecordItemBuybackRequest $request, RecordItemBuyback $action): RedirectResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        $member = $this->resolveMember($request->string('customer_id')->toString());

        if (! $member) {
            throw ValidationException::withMessages(['customer_id' => 'No member found with this Customer ID.']);
        }

        $metal = $request->string('metal')->toString();
        $currentRate = MetalRate::where('metal', $metal)
            ->whereDate('effective_from', '<=', now()->toDateString())
            ->orderByDesc('effective_from')
            ->first();

        if (! $currentRate) {
            throw ValidationException::withMessages(['metal' => "No {$metal} rate has been configured yet."]);
        }

        $action(
            $store,
            $member,
            $request->string('item_name')->toString(),
            $metal,
            (float) $request->input('weight'),
            $request->integer('quantity'),
            $request->string('description')->toString() ?: null,
            $currentRate,
            $request->user(),
        );

        return redirect()->route('admin.sales.index')->with('status', 'Item Buyback recorded.');
    }

    public function storeDelivery(RecordPlanJewelleryDeliveryRequest $request, RecordPlanJewelleryDelivery $action): RedirectResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        $member = $this->resolveMember($request->string('customer_id')->toString());

        if (! $member) {
            throw ValidationException::withMessages(['customer_id' => 'No member found with this Customer ID.']);
        }

        $benefit = $member->productBenefits()->whereNull('delivered_at')->first();

        if (! $benefit) {
            throw ValidationException::withMessages(['customer_id' => 'This member has no undelivered plan jewellery entitlement.']);
        }

        $action(
            $benefit,
            $store,
            (float) $request->input('sale_amount'),
            (float) ($request->input('gst_amount') ?? 0),
            $request->user(),
        );

        return redirect()->route('admin.sales.index')->with('status', 'Plan jewellery delivery recorded.');
    }

    private function resolveMember(?string $customerId): ?Member
    {
        if (! $customerId) {
            return null;
        }

        return Member::where('customer_id', $customerId)->first();
    }
}

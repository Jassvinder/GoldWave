<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Billing\GenerateStoreSaleBill;
use App\Actions\Billing\ResolveDeliverableBenefit;
use App\Actions\Payments\CollectCashPaymentViaStoreWallet;
use App\Actions\Store\ConfirmStoreSale;
use App\Actions\Store\PriceStoreSale;
use App\Actions\Store\RecordItemBuyback;
use App\Actions\Store\RecordPlanJewelleryDelivery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RecordItemBuybackRequest;
use App\Http\Requests\Admin\RecordPlanJewelleryDeliveryRequest;
use App\Http\Requests\Admin\RecordStoreSaleRequest;
use App\Models\Member;
use App\Models\MetalRate;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreInventoryItem;
use App\Models\StoreSale;
use App\Services\InvoicePresenter;
use App\Services\RuleVersionService;
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
    public function __construct(private readonly RuleVersionService $rules) {}

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
                'quantity' => $sale->quantity,
                'total_invoice_amount' => $sale->total_invoice_amount,
                'payment_source' => $sale->payment_source,
                'distribution_status' => $sale->distribution_status,
                'invoice_no' => $sale->invoice?->invoice_no,
            ]);

        // T-169 — what the sale form previews; the server re-prices with `PriceStoreSale` when the sale is recorded.
        $ratePreview = function (string $metal): ?array {
            $rate = PriceStoreSale::currentRate($metal);

            return $rate ? [
                'rate_per_gram' => $rate->rate_per_gram,
                'making_charge_percent' => $rate->making_charge_percent,
            ] : null;
        };

        return Inertia::render('admin/sales', [
            'inventory_items' => $inventoryItems,
            'recent_sales' => $recentSales,
            'collect_search' => $this->collectSearchResult($request),
            'pricing' => [
                'gold' => $ratePreview('gold'),
                'silver' => $ratePreview('silver'),
                'gst_percent' => (float) $this->rules->value('store_gst_percent', 0),
            ],
        ]);
    }

    /**
     * DOMAIN_LOGIC.md §12.2(a) — T-151. Looks up a member by Customer ID and
     * lists their pending cash payments (registration or EMI installment)
     * this store could collect and settle via its own Store Wallet. Kept on
     * the same page as a GET query-param search (`?collect_customer_id=`)
     * rather than a separate route, matching this page's existing
     * search-and-act pattern.
     *
     * @return array<string, mixed>|null
     */
    private function collectSearchResult(Request $request): ?array
    {
        $customerId = $request->string('collect_customer_id')->toString();

        if ($customerId === '') {
            return null;
        }

        $member = Member::where('customer_id', $customerId)->first();

        if (! $member) {
            return ['customer_id' => $customerId, 'found' => false, 'payments' => []];
        }

        $pending = $member->payments()
            ->where('mode', 'cash')
            ->where('status', 'pending')
            ->orderBy('id')
            ->get()
            ->map(fn ($payment): array => [
                'id' => $payment->id,
                'type' => $payment->type,
                'amount' => $payment->amount,
                'created_at' => $payment->created_at,
            ]);

        return [
            'customer_id' => $customerId,
            'found' => true,
            'member_name' => $member->placeholder_name ?? $member->user?->name,
            'payments' => $pending,
        ];
    }

    public function collectPayment(Payment $payment, Request $request, CollectCashPaymentViaStoreWallet $action): RedirectResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        $action($payment, $store, $request->user());

        return redirect()->route('admin.sales.index', ['collect_customer_id' => $payment->member?->customer_id])
            ->with('status', 'Payment collected and settled via Store Wallet.');
    }

    public function storeSale(RecordStoreSaleRequest $request, ConfirmStoreSale $action, PriceStoreSale $priceSale): RedirectResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        $customerId = $request->string('customer_id')->toString() ?: null;
        $member = $this->resolveMember($customerId);

        if ($customerId && ! $member) {
            throw ValidationException::withMessages(['customer_id' => 'No member found with this Customer ID.']);
        }

        $inventoryItem = null;

        if ($request->filled('store_inventory_item_id')) {
            $inventoryItem = StoreInventoryItem::where('store_id', $store->id)->findOrFail($request->integer('store_inventory_item_id'));
        }

        $itemName = $inventoryItem !== null ? $inventoryItem->item_name : $request->string('item_name')->toString();
        $metal = $inventoryItem !== null ? $inventoryItem->metal : $request->string('metal')->toString();
        // A tracked item always sells at its own recorded weight; only a manual item's weight comes from the form.
        $weight = $inventoryItem !== null ? (float) $inventoryItem->weight : (float) $request->input('item_weight');
        $quantity = $request->integer('quantity');

        // T-169 — priced by the server at today's rate; the store cannot type or change the amount.
        $price = $priceSale($metal, $weight, $quantity);

        $action(
            store: $store,
            member: $member,
            transactionType: $request->string('transaction_type')->toString(),
            itemName: $itemName,
            inventoryItem: $inventoryItem,
            itemWeight: $weight,
            quantity: $quantity,
            rate: $price['rate_per_gram'],
            saleAmount: $price['subtotal'],
            gstAmount: $price['gst_amount'],
            paymentSource: $request->string('payment_source')->toString(),
            operator: $request->user(),
            metal: $metal,
            price: $price,
        );

        return redirect()->route('admin.sales.index')->with('status', 'Sale recorded. Use "Generate bill" in Recent Sales whenever the customer wants a bill.');
    }

    public function storeBuyback(RecordItemBuybackRequest $request, RecordItemBuyback $action): RedirectResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        // T-149 follow-up (23-09-2026) — a Buyback seller is primarily a
        // non-member walk-in; a Customer ID is only resolved (and required
        // to match) when the operator actually gave one.
        $customerId = $request->string('customer_id')->toString() ?: null;
        $member = $this->resolveMember($customerId);

        if ($customerId && ! $member) {
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
            $member ? null : ($request->string('walk_in_name')->toString() ?: null),
            $member ? null : ($request->string('walk_in_mobile')->toString() ?: null),
        );

        return redirect()->route('admin.sales.index')->with('status', 'Item Buyback recorded.');
    }

    public function storeDelivery(RecordPlanJewelleryDeliveryRequest $request, RecordPlanJewelleryDelivery $action, ResolveDeliverableBenefit $resolveBenefit): RedirectResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        $member = $this->resolveMember($request->string('customer_id')->toString());

        if (! $member) {
            throw ValidationException::withMessages(['customer_id' => 'No member found with this Customer ID.']);
        }

        // T-171 — an EMI member's entitlement becomes deliverable once the last EMI is paid.
        $benefit = $resolveBenefit($member);

        $item = StoreInventoryItem::where('store_id', $store->id)->findOrFail($request->integer('store_inventory_item_id'));

        $action($benefit, $store, $item, $request->user());

        return redirect()->route('admin.sales.index')->with('status', 'Plan jewellery delivery recorded.');
    }

    /**
     * T-160 (28-09-2026) — DOMAIN_LOGIC.md §16.2: every confirmed sale's invoice
     * can be printed or shared via WhatsApp. A standalone page (no portal
     * chrome) so the browser's Print / Save as PDF gives a clean invoice.
     */
    public function invoice(StoreSale $sale, Request $request): Response
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        abort_if($sale->store_id !== $store->id, 404);
        abort_if(! $sale->invoice()->exists(), 404);

        return Inertia::render('invoices/show', [
            // T-171 — "original" only right after generating; any later view is a duplicate copy.
            'invoice' => InvoicePresenter::forStoreSale($sale, (bool) session('bill_original')),
            'back_url' => route('admin.sales.index'),
        ]);
    }

    /** T-171 (28-09-2026) — "Generate bill", at any time after the sale, with optional hallmarking per piece. */
    public function generateBill(StoreSale $sale, Request $request, GenerateStoreSaleBill $generate): RedirectResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        abort_if($sale->store_id !== $store->id, 404);

        $generate($sale, $request->user(), $this->hallmarkInput($request));

        return redirect()->route('admin.sales.invoice', $sale)->with('bill_original', true);
    }

    /**
     * The hallmark list from a bill form: only when "Hallmarked (HUID)" is ticked.
     *
     * @return list<array{huid: string, charge: string}>
     */
    public static function hallmarkInput(Request $request): array
    {
        if (! $request->boolean('hallmarked')) {
            return [];
        }

        $request->validate([
            'hallmarks' => ['required', 'array', 'min:1'],
            'hallmarks.*.huid' => ['required', 'string', 'max:16'],
            'hallmarks.*.charge' => ['required', 'numeric', 'min:0'],
        ]);

        return array_values(array_map(
            fn ($piece): array => ['huid' => (string) ($piece['huid'] ?? ''), 'charge' => (string) ($piece['charge'] ?? '0')],
            (array) $request->input('hallmarks', []),
        ));
    }

    private function resolveMember(?string $customerId): ?Member
    {
        if (! $customerId) {
            return null;
        }

        return Member::where('customer_id', $customerId)->first();
    }
}

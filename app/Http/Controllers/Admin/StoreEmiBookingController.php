<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Store\DeliverStoreEmiBooking;
use App\Actions\Store\QuoteStoreEmiBooking;
use App\Actions\Store\RequestStoreEmiBooking;
use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Store;
use App\Models\StoreEmiBooking;
use App\Models\StoreInventoryItem;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-185a (DOMAIN_LOGIC.md §16.13) — the Store Admin's "Repurchase on EMI" page: request one piece of this store's stock
 * for a member over 10 or 20 Current Rate EMIs (Super Admin approves), and follow this store's requests.
 */
class StoreEmiBookingController extends Controller
{
    public function index(Request $request, QuoteStoreEmiBooking $quote): Response
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        $items = $store->inventoryItems()
            ->where('quantity', '>', 0)
            ->orderBy('item_name')
            ->get()
            ->map(function (StoreInventoryItem $item) use ($quote): array {
                $estimates = [];

                foreach (StoreEmiBooking::INSTALLMENT_COUNTS as $count) {
                    try {
                        $q = $quote($item, $count);
                        $estimates[$count] = [
                            'total_value' => $q['total_value'],
                            'first_installment' => $q['installment_amounts'][0],
                            'last_installment' => $q['installment_amounts'][count($q['installment_amounts']) - 1],
                        ];
                    } catch (ValidationException) {
                        $estimates[$count] = null; // e.g. no rate set for this metal yet.
                    }
                }

                return [
                    'id' => $item->id,
                    'item_name' => $item->item_name,
                    'metal' => $item->metal,
                    'weight' => (float) $item->weight,
                    'quantity' => $item->quantity,
                    'estimates' => $estimates,
                ];
            });

        $bookings = StoreEmiBooking::with(['member.user', 'emiSchedule.installments'])
            ->where('store_id', $store->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (StoreEmiBooking $booking): array => [
                'id' => $booking->id,
                'requested_at' => Dates::date($booking->created_at),
                'member' => ['customer_id' => $booking->member->customer_id, 'name' => $booking->member->user?->name],
                'item_name' => $booking->item_name,
                'metal' => $booking->metal,
                'weight_grams' => (float) $booking->weight_grams,
                'installment_count' => $booking->installment_count,
                'status' => $booking->status,
                'paid_installments' => $booking->emiSchedule?->installments->where('status', 'paid')->count(),
                'cancel_message' => $booking->cancel_message,
                // T-185b/c — what is owed after a break.
                'silver_grams_owed' => $booking->silver_grams_owed,
            ]);

        return Inertia::render('admin/store-emi', [
            'items' => $items,
            'bookings' => $bookings,
        ]);
    }

    public function store(Request $request, RequestStoreEmiBooking $action): RedirectResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        $data = $request->validate([
            'customer_id' => ['required', 'string', 'max:30'],
            'inventory_item_id' => ['required', 'integer'],
            'installment_count' => ['required', 'integer', 'in:10,20'],
        ]);

        $item = StoreInventoryItem::where('store_id', $store->id)->whereKey((int) $data['inventory_item_id'])->first();

        if ($item === null) {
            throw ValidationException::withMessages(['inventory_item_id' => 'Choose a piece from your own store.']);
        }

        $action(
            $store,
            $request->user(),
            Member::where('customer_id', trim($data['customer_id']))->first(),
            $item,
            (int) $data['installment_count'],
        );

        return back()->with('status', 'Request sent — Super Admin will approve it and lock the rate.');
    }

    /** T-185c — hand the fully paid piece over; the member pays only GST (and any hallmark added at billing). */
    public function deliverPiece(Request $request, StoreEmiBooking $booking, DeliverStoreEmiBooking $deliver): RedirectResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        $sale = $deliver->piece($booking, $store, $request->user());

        return redirect()->route('admin.sales.index')->with('status', $this->collectMessage($sale->total_invoice_amount, $sale->prepaid_amount));
    }

    /** T-185c — hand the silver owed after a break over, as a silver piece of at least that weight. */
    public function deliverSilver(Request $request, StoreEmiBooking $booking, DeliverStoreEmiBooking $deliver): RedirectResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        $data = $request->validate(['inventory_item_id' => ['required', 'integer']]);
        $item = StoreInventoryItem::where('store_id', $store->id)->whereKey((int) $data['inventory_item_id'])->first();

        if ($item === null) {
            throw ValidationException::withMessages(['inventory_item_id' => 'Choose a silver piece from your own store.']);
        }

        $sale = $deliver->silver($booking, $store, $item, $request->user());

        return redirect()->route('admin.sales.index')->with('status', $this->collectMessage($sale->total_invoice_amount, $sale->prepaid_amount));
    }

    private function collectMessage(mixed $total, mixed $prepaid): string
    {
        $due = number_format((float) $total - (float) $prepaid, 2);

        return "Handed over. Collect ₹{$due} from the member now (hallmarking added on the bill increases it). Use \"Generate bill\" in Recent Sales for the bill.";
    }
}

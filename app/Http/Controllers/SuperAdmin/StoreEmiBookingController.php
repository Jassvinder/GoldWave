<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Store\DecideStoreEmiBooking;
use App\Actions\Store\QuoteStoreEmiBooking;
use App\Http\Controllers\Controller;
use App\Models\StoreEmiBooking;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-185a (DOMAIN_LOGIC.md §16.13) — Super Admin's (and the company Admin's) queue of Repurchase on EMI requests. Each
 * pending card shows what approving **now** would lock (today's rate, the EMIs) and the piece held from the store's
 * stock; Approve applies it, Cancel sends a message to the member and the Store Admin.
 */
class StoreEmiBookingController extends Controller
{
    public function index(QuoteStoreEmiBooking $quote): Response
    {
        $pending = StoreEmiBooking::with(['member.user', 'store', 'inventoryItem', 'requestedBy'])
            ->where('status', 'pending')
            ->orderBy('id')
            ->get()
            ->map(function (StoreEmiBooking $booking) use ($quote): array {
                $now = null;
                $blocked = null;

                try {
                    if ($booking->inventoryItem->quantity < 1) {
                        throw ValidationException::withMessages(['booking' => 'The piece is out of stock at the store.']);
                    }

                    $q = $quote($booking->inventoryItem, (int) $booking->installment_count);
                    $amounts = $q['installment_amounts'];
                    $now = [
                        'rate_per_gram' => $q['rate_per_gram'],
                        'metal_value' => $q['metal_value'],
                        'making_charges' => $q['making_charges'],
                        'total_value' => $q['total_value'],
                        'first_installment' => $amounts[0],
                        'last_installment' => $amounts[count($amounts) - 1],
                        'total_payable' => round(array_sum($amounts), 2),
                    ];
                } catch (ValidationException $e) {
                    $blocked = (string) collect($e->errors())->flatten()->first();
                }

                return [
                    ...$this->row($booking),
                    'store_stock' => $booking->inventoryItem->quantity,
                    'quote' => $now,
                    'blocked_reason' => $blocked,
                ];
            });

        $history = StoreEmiBooking::with(['member.user', 'store', 'requestedBy', 'decidedBy'])
            ->where('status', '!=', 'pending')
            ->orderByDesc('decided_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (StoreEmiBooking $booking): array => [
                ...$this->row($booking),
                'status' => $booking->status,
                'decided_at' => Dates::date($booking->decided_at),
                'decided_by' => $booking->decidedBy?->name,
                'cancel_message' => $booking->cancel_message,
            ]);

        return Inertia::render('super-admin/store-emi-bookings', [
            'pending' => $pending,
            'history' => $history,
        ]);
    }

    public function approve(Request $request, StoreEmiBooking $booking, DecideStoreEmiBooking $decide): RedirectResponse
    {
        $decide->approve($booking, $request->user());

        return back()->with('status', 'Approved — the piece is held and the member\'s EMIs are ready.');
    }

    public function cancel(Request $request, StoreEmiBooking $booking, DecideStoreEmiBooking $decide): RedirectResponse
    {
        $data = $request->validate([
            'cancel_message' => ['required', 'string', 'max:500'],
        ]);

        $decide->cancel($booking, $request->user(), $data['cancel_message']);

        return back()->with('status', 'Request cancelled — the member and the store will see your message.');
    }

    /** @return array<string, mixed> */
    private function row(StoreEmiBooking $booking): array
    {
        return [
            'id' => $booking->id,
            'requested_at' => Dates::date($booking->created_at),
            'member' => [
                'customer_id' => $booking->member->customer_id,
                'name' => $booking->member->user?->name,
            ],
            'store' => $booking->store->name,
            'requested_by' => $booking->requestedBy->name,
            'item_name' => $booking->item_name,
            'metal' => $booking->metal,
            'weight_grams' => (float) $booking->weight_grams,
            'installment_count' => $booking->installment_count,
        ];
    }
}

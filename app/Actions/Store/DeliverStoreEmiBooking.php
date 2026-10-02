<?php

namespace App\Actions\Store;

use App\Models\EmiRateBookingEvent;
use App\Models\Store;
use App\Models\StoreEmiBooking;
use App\Models\StoreInventoryItem;
use App\Models\StoreRestockShipment;
use App\Models\StoreSale;
use App\Models\User;
use App\Services\RuleVersionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §16.13 point 4 (T-185c) — the Store Admin hands a Repurchase on EMI over at the booking's store. It is
 * recorded as an ordinary confirmed store sale (so "Generate bill", hallmarking and the invoice work unchanged) that
 * points to the booking, carries `prepaid_amount` — what the EMIs already paid — and never generates income.
 *
 * - **piece()** — a fully paid booking (`completed`): the held piece (its stock already went down at approval) at the
 *   **locked** rate and making from the booking; the EMIs prepaid metal value + making, so the member pays only GST (and
 *   any hallmark added at billing) at the counter.
 * - **silver()** — a broken booking: a silver piece from this store's stock whose weight is **at least** the grams owed,
 *   priced at **today's** rate and making (T-169); the owed grams are prepaid at today's rate, so the member pays the extra
 *   grams, the making and GST (user decision 30-09-2026).
 *
 * The store never received the EMI money, so — the T-152 rule — the company owes it a restock of the prepaid metal value.
 */
class DeliverStoreEmiBooking
{
    public function __construct(
        private readonly ConfirmStoreSale $confirmStoreSale,
        private readonly PriceStoreSale $priceSale,
        private readonly RuleVersionService $rules,
    ) {}

    public function piece(StoreEmiBooking $booking, Store $store, User $operator): StoreSale
    {
        return DB::transaction(function () use ($booking, $store, $operator) {
            $locked = $this->lock($booking, $store, 'completed', 'Only a fully paid Repurchase on EMI can be handed over.');

            $details = EmiRateBookingEvent::where('emi_schedule_id', $locked->emi_schedule_id)->where('event', 'booked')->latest('id')->firstOrFail()->details ?? [];
            $metalValue = round((float) ($details['metal_value'] ?? 0), 2);
            $making = round((float) ($details['making_charges'] ?? 0), 2);
            $subtotal = round($metalValue + $making, 2);
            $gstPercent = (float) $this->rules->value('store_gst_percent', 0);
            $gstAmount = round($subtotal * $gstPercent / 100, 2);
            $schedule = $locked->emiSchedule()->firstOrFail();

            $price = [
                'metal_rate_id' => (int) $schedule->metal_rate_id,
                'rate_per_gram' => (float) $schedule->rate_per_gram_at_booking,
                'metal_value' => $metalValue,
                'making_charge_percent' => (float) ($details['making_charge_percent'] ?? 0),
                'making_charges' => $making,
                'hallmark_charges' => 0.0,
                'subtotal' => $subtotal,
                'gst_percent' => $gstPercent,
                'gst_amount' => $gstAmount,
                'total' => round($subtotal + $gstAmount, 2),
            ];

            return $this->record($locked, $store, $operator, $locked->item_name, (string) $locked->metal, (float) $locked->weight_grams, null, $price, $subtotal, $metalValue, (float) $locked->weight_grams);
        });
    }

    public function silver(StoreEmiBooking $booking, Store $store, StoreInventoryItem $item, User $operator): StoreSale
    {
        return DB::transaction(function () use ($booking, $store, $item, $operator) {
            $locked = $this->lock($booking, $store, 'broken', 'Only a broken Repurchase on EMI with silver owed can be handed over this way.');
            $grams = (float) $locked->silver_grams_owed;

            if ($grams <= 0) {
                throw ValidationException::withMessages(['booking' => 'Nothing is owed on this booking.']);
            }

            if ($item->store_id !== $store->id || $item->metal !== 'silver' || $item->quantity < 1) {
                throw ValidationException::withMessages(['inventory_item_id' => 'Choose a silver piece in stock at your store.']);
            }

            if ((float) $item->weight < $grams) {
                throw ValidationException::withMessages(['inventory_item_id' => "Choose a silver piece of at least {$locked->silver_grams_owed} g."]);
            }

            $price = ($this->priceSale)('silver', (float) $item->weight, 1);
            $prepaid = round($grams * $price['rate_per_gram'], 2);

            return $this->record($locked, $store, $operator, $item->item_name, 'silver', (float) $item->weight, $item, $price, $prepaid, $prepaid, $grams);
        });
    }

    private function lock(StoreEmiBooking $booking, Store $store, string $status, string $message): StoreEmiBooking
    {
        $locked = StoreEmiBooking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

        if ($locked->store_id !== $store->id) {
            throw ValidationException::withMessages(['booking' => 'This Repurchase on EMI belongs to another store.']);
        }

        if ($locked->status !== $status) {
            throw ValidationException::withMessages(['booking' => $message]);
        }

        return $locked;
    }

    /** @param array{metal_rate_id: int, rate_per_gram: float, metal_value: float, making_charge_percent: float, making_charges: float, hallmark_charges: float, subtotal: float, gst_percent: float, gst_amount: float, total: float} $price */
    private function record(StoreEmiBooking $booking, Store $store, User $operator, string $itemName, string $metal, float $weight, ?StoreInventoryItem $item, array $price, float $prepaid, float $restockValue, float $restockWeight): StoreSale
    {
        $sale = ($this->confirmStoreSale)(
            store: $store,
            member: $booking->member()->firstOrFail(),
            transactionType: 'repurchase',
            itemName: $itemName,
            inventoryItem: $item,
            itemWeight: $weight,
            quantity: 1,
            rate: $price['rate_per_gram'],
            saleAmount: $price['subtotal'],
            gstAmount: $price['gst_amount'],
            paymentSource: 'other',
            operator: $operator,
            metal: $metal,
            price: $price,
            storeEmiBookingId: $booking->id,
            prepaidAmount: $prepaid,
        );

        StoreRestockShipment::create([
            'store_id' => $store->id,
            'item_name' => $itemName,
            'metal' => $metal,
            'weight' => $restockWeight,
            'value' => $restockValue,
            'status' => 'owed',
        ]);

        $booking->update(['status' => 'delivered', 'delivered_at' => now(), 'delivered_by' => $operator->id]);

        return $sale;
    }
}

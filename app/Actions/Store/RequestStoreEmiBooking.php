<?php

namespace App\Actions\Store;

use App\Models\Member;
use App\Models\Store;
use App\Models\StoreEmiBooking;
use App\Models\StoreInventoryItem;
use App\Models\User;
use App\Notifications\StoreEmiBookingRequested;
use App\Services\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §16.13 point 1 (T-185a) — a Store Admin asks for a member's Repurchase on EMI: one piece of **their own
 * store's** stock over 10 or 20 Current Rate EMIs. Nothing is held or priced for real yet; the estimate is today's
 * figures, and Super Admin's approval (`DecideStoreEmiBooking`) fixes them on the approval day. A member may have only
 * one open store EMI at a time. Super Admins are told at once.
 */
class RequestStoreEmiBooking
{
    public function __construct(private readonly QuoteStoreEmiBooking $quote) {}

    public function __invoke(Store $store, User $storeAdmin, ?Member $member, StoreInventoryItem $item, int $installmentCount): StoreEmiBooking
    {
        if ($member === null || $member->status !== 'active' || $member->customer_id === null) {
            throw ValidationException::withMessages(['customer_id' => 'A Repurchase on EMI is only for an active member — enter their Customer ID.']);
        }

        if ($item->store_id !== $store->id) {
            throw ValidationException::withMessages(['inventory_item_id' => 'Choose a piece from your own store.']);
        }

        $booking = DB::transaction(function () use ($store, $storeAdmin, $member, $item, $installmentCount) {
            Member::whereKey($member->id)->lockForUpdate()->first();

            if (StoreEmiBooking::where('member_id', $member->id)->open()->exists()) {
                throw ValidationException::withMessages(['customer_id' => 'This member already has a Repurchase on EMI that is pending, running or not yet delivered.']);
            }

            if ($item->quantity < 1) {
                throw ValidationException::withMessages(['inventory_item_id' => 'This piece is out of stock.']);
            }

            $quote = ($this->quote)($item, $installmentCount);

            return StoreEmiBooking::create([
                'member_id' => $member->id,
                'store_id' => $store->id,
                'store_inventory_item_id' => $item->id,
                'requested_by' => $storeAdmin->id,
                'item_name' => $item->item_name,
                'metal' => $item->metal,
                'weight_grams' => $item->weight,
                'installment_count' => $installmentCount,
                'status' => 'pending',
                'estimate' => [
                    'rate_per_gram' => $quote['rate_per_gram'],
                    'total_value' => $quote['total_value'],
                    'first_installment' => $quote['installment_amounts'][0],
                    'last_installment' => $quote['installment_amounts'][count($quote['installment_amounts']) - 1],
                ],
            ]);
        });

        Notifier::toSuperAdmins(new StoreEmiBookingRequested($booking));

        return $booking;
    }
}

<?php

namespace App\Actions\Store;

use App\Models\Payment;
use App\Models\ProductBenefit;
use App\Models\Store;
use App\Models\StoreInventoryItem;
use App\Models\StoreRestockShipment;
use App\Models\StoreSale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §16.10 — marks a member's plan-jewellery entitlement
 * (`product_benefits`) as physically handed over through a specific store,
 * which is what actually triggers §16.4 scenario 3's Store Profit
 * Distribution for it. A `product_benefits` row never marked delivered
 * through a store (handed over elsewhere, or not yet due) never generates a
 * store attribution — this Action is the only place that wiring happens.
 * Reuses `ConfirmStoreSale` rather than duplicating sale-creation logic, so
 * the resulting `store_sales` row is indistinguishable from any other
 * confirmed sale once created.
 *
 * T-152 (23-09-2026, user decision, DOMAIN_LOGIC.md §16.12) — also decides
 * whether this delivery owes the store a restock: the member's own
 * registration payment tells us whether *this* store ever actually received
 * the money (`payments.paying_store_id`, §12.2(a)). If it did not — paid
 * online, paid cash with no store involved, or paid cash but settled
 * through a *different* store — a `StoreRestockShipment` (`status=owed`) is
 * created in the same transaction as the delivery itself.
 */
class RecordPlanJewelleryDelivery
{
    public function __construct(
        private readonly ConfirmStoreSale $confirmStoreSale,
        private readonly PriceStoreSale $priceSale,
    ) {}

    /**
     * T-168 (28-09-2026, user decision) — the delivered piece is chosen from this store's inventory and its stock
     * goes down, and the delivery is priced like any purchase (`PriceStoreSale`): at the rate locked when a
     * Current Rate member booked, otherwise at today's rate; making % is always today's (DOMAIN_LOGIC.md §21).
     * Everything happens in one transaction, so a refused sale (e.g. out of stock) never leaves the entitlement
     * marked as delivered.
     */
    public function __invoke(
        ProductBenefit $productBenefit,
        Store $store,
        StoreInventoryItem $item,
        User $operator,
    ): StoreSale {
        if ($productBenefit->delivered_at) {
            throw ValidationException::withMessages([
                'product_benefit' => 'This plan entitlement has already been recorded as delivered.',
            ]);
        }

        if ($item->store_id !== $store->id) {
            throw ValidationException::withMessages(['store_inventory_item_id' => 'This item is not in your store.']);
        }

        $member = $productBenefit->member()->firstOrFail();
        $plan = $productBenefit->membershipPlan()->firstOrFail();

        if ($plan->product_category !== null && $item->metal !== $plan->product_category) {
            throw ValidationException::withMessages([
                'store_inventory_item_id' => "This member's plan is {$plan->product_category} jewellery — choose a {$plan->product_category} item.",
            ]);
        }

        $schedule = $member->emiSchedule()->first();
        $lockedRate = $schedule?->rate_booking_method === 'current_rate' && $schedule->rate_per_gram_at_booking !== null
            ? (float) $schedule->rate_per_gram_at_booking
            : null;

        $price = ($this->priceSale)($item->metal, (float) $item->weight, 1, $lockedRate, $schedule?->metal_rate_id);

        return DB::transaction(function () use ($productBenefit, $store, $item, $operator, $member, $plan, $price) {
            $productBenefit->update([
                'store_id' => $store->id,
                'delivered_at' => now(),
            ]);

            $registrationPayment = Payment::where('member_id', $member->id)
                ->where('type', 'registration')
                ->where('status', 'paid')
                ->first();

            $thisStoreWasPaid = $registrationPayment !== null && $registrationPayment->paying_store_id === $store->id;

            if (! $thisStoreWasPaid) {
                StoreRestockShipment::create([
                    'store_id' => $store->id,
                    'product_benefit_id' => $productBenefit->id,
                    'item_name' => $item->item_name,
                    'metal' => $item->metal,
                    'weight' => $item->weight,
                    'value' => $price['metal_value'],
                    'status' => 'owed',
                ]);
            }

            return ($this->confirmStoreSale)(
                store: $store,
                member: $member,
                transactionType: 'new_sale',
                itemName: $item->item_name,
                inventoryItem: $item,
                itemWeight: (float) $item->weight,
                quantity: 1,
                rate: $price['rate_per_gram'],
                saleAmount: $price['subtotal'],
                gstAmount: $price['gst_amount'],
                paymentSource: 'other',
                operator: $operator,
                metal: $plan->product_category ?? $item->metal,
                price: $price,
            );
        });
    }
}

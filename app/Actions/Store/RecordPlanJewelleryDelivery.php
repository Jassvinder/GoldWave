<?php

namespace App\Actions\Store;

use App\Models\Payment;
use App\Models\ProductBenefit;
use App\Models\Store;
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
    public function __construct(private readonly ConfirmStoreSale $confirmStoreSale) {}

    public function __invoke(
        ProductBenefit $productBenefit,
        Store $store,
        float $saleAmount,
        float $gstAmount,
        User $operator,
    ): StoreSale {
        if ($productBenefit->delivered_at) {
            throw ValidationException::withMessages([
                'product_benefit' => 'This plan entitlement has already been recorded as delivered.',
            ]);
        }

        $member = $productBenefit->member()->firstOrFail();
        $plan = $productBenefit->membershipPlan()->firstOrFail();

        DB::transaction(function () use ($productBenefit, $store, $member, $plan, $saleAmount) {
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
                    'item_name' => "Plan jewellery — {$plan->name}",
                    'metal' => $plan->product_category,
                    'weight' => $plan->fixed_weight_grams,
                    'value' => $saleAmount,
                    'status' => 'owed',
                ]);
            }
        });

        return ($this->confirmStoreSale)(
            store: $store,
            member: $member,
            transactionType: 'new_sale',
            itemName: "Plan jewellery — {$plan->name}",
            inventoryItem: null,
            itemWeight: $plan->fixed_weight_grams !== null ? (float) $plan->fixed_weight_grams : null,
            quantity: 1,
            rate: $productBenefit->rate_per_gram_at_entry !== null ? (float) $productBenefit->rate_per_gram_at_entry : null,
            saleAmount: $saleAmount,
            gstAmount: $gstAmount,
            paymentSource: 'other',
            operator: $operator,
            metal: $plan->product_category,
        );
    }
}

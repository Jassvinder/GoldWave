<?php

namespace App\Actions\Store;

use App\Events\StoreSaleConfirmed;
use App\Models\Member;
use App\Models\Store;
use App\Models\StoreInventoryItem;
use App\Models\StoreSale;
use App\Models\User;
use App\Services\StoreActivityLogger;
use App\Services\StoreWalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §16.2 — records a confirmed New Sale / Purchase /
 * Repurchase. `transactionType` still accepts all 3 values — this Action is
 * shared by the manual Sales-page entry point (`SalesController::storeSale`,
 * which as of 23-09-2026 only offers Purchase/Repurchase — `new_sale` is
 * reserved for the automatic path below) and `RecordPlanJewelleryDelivery`
 * (always `new_sale`, always with a member, §16.10). When the sale draws
 * from tracked inventory (§16.5), the item's stock is decremented atomically
 * with the sale and the transaction is blocked if it would exceed the
 * item's current quantity (§16.8, the same insufficient-balance-blocks-the-
 * transaction pattern already used for Store Wallet deductions, §16.1). When
 * `paymentSource` is `store_wallet`, the deduction and the sale confirmation
 * commit in the same transaction (§16.1). Fires `StoreSaleConfirmed` so
 * Store Profit Distribution and Purchase/Repurchase Upline Income process
 * independently (§16.4/§15) — the latter only when `member` is given.
 *
 * @phpstan-import-type SalePrice from PriceStoreSale
 */
class ConfirmStoreSale
{
    public function __construct(
        private readonly StoreWalletService $storeWallet,
        private readonly StoreActivityLogger $activityLog,
    ) {}

    /** @param SalePrice|null $price */
    public function __invoke(
        Store $store,
        ?Member $member,
        string $transactionType,
        string $itemName,
        ?StoreInventoryItem $inventoryItem,
        ?float $itemWeight,
        int $quantity,
        ?float $rate,
        float $saleAmount,
        float $gstAmount,
        string $paymentSource,
        User $operator,
        ?string $metal = null,
        // T-169 — the server-side price breakdown (`PriceStoreSale`), when the sale was priced automatically.
        ?array $price = null,
        // T-185c — a Repurchase on EMI handover, and the part of the bill its EMIs already paid.
        ?int $storeEmiBookingId = null,
        ?float $prepaidAmount = null,
    ): StoreSale {
        // Revised 23-09-2026 (user decision) — a repurchase is always an
        // existing member's own repeat purchase, never a walk-in's; a
        // purchase/new_sale may be either (§16.4 scenario 1 vs 2/3), so this
        // guard applies to repurchase only. Defense-in-depth: the manual
        // entry point (SalesController::storeSale) already enforces this at
        // the Request layer, but this Action is the real source of truth.
        if ($transactionType === 'repurchase' && $member === null) {
            throw ValidationException::withMessages([
                'customer_id' => 'A repurchase requires an existing member.',
            ]);
        }

        // T-110 (19-09-2026) — Gold/Silver compensation split needs to know
        // this sale's metal; a tracked inventory item already knows its own,
        // otherwise the caller (a manual/no-inventory sale, or
        // RecordPlanJewelleryDelivery) must supply it explicitly — never
        // silently assumed.
        $resolvedMetal = $metal;

        if ($inventoryItem !== null) {
            $resolvedMetal = $inventoryItem->metal;
        }

        if ($resolvedMetal === null) {
            throw ValidationException::withMessages([
                'metal' => 'Select which metal this sale is for.',
            ]);
        }

        $storeSale = DB::transaction(function () use (
            $store, $member, $transactionType, $itemName, $inventoryItem, $itemWeight,
            $quantity, $rate, $saleAmount, $gstAmount, $paymentSource, $operator, $resolvedMetal, $price,
            $storeEmiBookingId, $prepaidAmount,
        ) {
            if ($inventoryItem) {
                $lockedItem = StoreInventoryItem::whereKey($inventoryItem->id)->lockForUpdate()->firstOrFail();

                if ($quantity > $lockedItem->quantity) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Insufficient stock for this item.',
                    ]);
                }

                $lockedItem->decrement('quantity', $quantity);
            }

            $totalInvoiceAmount = round($saleAmount + $gstAmount, 2);
            $storeWalletDeductionId = null;

            if ($paymentSource === 'store_wallet') {
                $wallet = $store->wallet()->firstOrFail();
                $deduction = $this->storeWallet->deduct(
                    $wallet,
                    $totalInvoiceAmount,
                    $operator,
                    "Store-initiated {$transactionType} — {$itemName}",
                );
                $storeWalletDeductionId = $deduction->id;
            }

            $storeSale = StoreSale::create([
                'store_id' => $store->id,
                'member_id' => $member?->id,
                'store_inventory_item_id' => $inventoryItem?->id,
                'transaction_type' => $transactionType,
                'item_name' => $itemName,
                'metal' => $resolvedMetal,
                'item_weight' => $itemWeight,
                'quantity' => $quantity,
                'rate' => $rate,
                'metal_rate_id' => $price['metal_rate_id'] ?? null,
                'metal_value' => $price['metal_value'] ?? null,
                'making_charge_percent' => $price['making_charge_percent'] ?? null,
                'making_charges' => $price['making_charges'] ?? null,
                'hallmark_charges' => $price['hallmark_charges'] ?? null,
                'gst_percent' => $price['gst_percent'] ?? null,
                'sale_amount' => $saleAmount,
                'gst_amount' => $gstAmount,
                'total_invoice_amount' => $totalInvoiceAmount,
                'payment_source' => $paymentSource,
                'store_wallet_deduction_id' => $storeWalletDeductionId,
                'distribution_status' => 'pending',
                'status' => 'confirmed',
                'store_emi_booking_id' => $storeEmiBookingId,
                'prepaid_amount' => $prepaidAmount,
            ]);

            // T-171 (28-09-2026) — no bill here any more: it is generated on request ("Generate bill",
            // `GenerateStoreSaleBill`), when the hallmark details are known.
            $this->activityLog->record($store, $operator, "store_sale_{$transactionType}", $member, $storeSale);

            return $storeSale;
        });

        StoreSaleConfirmed::dispatch($storeSale->fresh());

        return $storeSale->fresh(['invoice']);
    }
}

<?php

namespace App\Actions\Store;

use App\Models\Store;
use App\Models\StoreWallet;
use App\Models\User;
use App\Services\StoreActivityLogger;
use App\Services\StoreWalletService;
use Illuminate\Support\Facades\DB;

/**
 * DOMAIN_LOGIC.md §16.1 — Store creation and wallet control belong to Super
 * Admin. Records the jewellery allocation value (inventory/financial record,
 * kept separate from the Store Wallet balance — never substituted for one
 * another) and credits the company-held advance amount to a brand-new Store
 * Wallet as the store's available payment balance.
 */
class CreateStore
{
    public function __construct(
        private readonly StoreWalletService $storeWallet,
        private readonly StoreActivityLogger $activityLog,
    ) {}

    public function __invoke(
        string $name,
        ?User $owner,
        ?string $contact,
        ?string $location,
        float $jewelleryAllocationValue,
        float $advanceAmount,
        User $operator,
    ): Store {
        return DB::transaction(function () use ($name, $owner, $contact, $location, $jewelleryAllocationValue, $advanceAmount, $operator) {
            $store = Store::create([
                'name' => $name,
                'owner_user_id' => $owner?->id,
                'contact' => $contact,
                'location' => $location,
                'status' => 'active',
                'jewellery_allocation_value' => $jewelleryAllocationValue,
                'advance_amount' => $advanceAmount,
            ]);

            $wallet = StoreWallet::create([
                'store_id' => $store->id,
                'balance' => 0,
                'status' => 'active',
            ]);

            if ($advanceAmount > 0) {
                $this->storeWallet->advanceCredit($wallet, $advanceAmount, $operator);
            }

            $this->activityLog->record($store, $operator, 'store_created', null, $store);

            return $store->fresh(['wallet']);
        });
    }
}

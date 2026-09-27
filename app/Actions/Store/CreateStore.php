<?php

namespace App\Actions\Store;

use App\Models\Store;
use App\Models\StoreWallet;
use App\Models\User;
use App\Services\StoreActivityLogger;
use App\Services\StoreCodeGenerator;
use App\Services\StoreWalletService;
use Illuminate\Support\Facades\DB;

/**
 * DOMAIN_LOGIC.md §16.1 — Store creation and wallet control belong to Super
 * Admin. Records the jewellery allocation value (inventory/financial record,
 * kept separate from the Store Wallet balance — never substituted for one
 * another) and credits the company-held advance amount to a brand-new Store
 * Wallet as the store's available payment balance.
 *
 * T-117 (19-09-2026) — every store gets its own permanent `store_code`
 * (`GWLST0001…`), regardless of whether it has an owner yet. `$password` is
 * the caller's already-decided plaintext (auto-generated or manually typed
 * by the Super Admin) — only meaningful, and only set, when an owner is
 * given at the same time; a store with no owner yet has nothing to log in
 * as. This Action never decides auto-vs-manual itself, only hashes and
 * stores whatever it's handed.
 */
class CreateStore
{
    public function __construct(
        private readonly StoreWalletService $storeWallet,
        private readonly StoreActivityLogger $activityLog,
        private readonly StoreCodeGenerator $storeCode,
    ) {}

    public function __invoke(
        string $name,
        ?User $owner,
        ?string $contact,
        ?string $location,
        float $jewelleryAllocationValue,
        float $advanceAmount,
        User $operator,
        ?string $password = null,
    ): Store {
        return DB::transaction(function () use ($name, $owner, $contact, $location, $jewelleryAllocationValue, $advanceAmount, $operator, $password) {
            $store = Store::create([
                'name' => $name,
                'store_code' => $this->storeCode->next(),
                'password' => $owner !== null ? $password : null,
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

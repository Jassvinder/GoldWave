<?php

namespace App\Actions\Store;

use App\Models\StoreWallet;
use App\Models\StoreWalletLedgerEntry;
use App\Models\User;
use App\Services\StoreActivityLogger;
use App\Services\StoreWalletService;

/**
 * DOMAIN_LOGIC.md §16.1 — the Store Owner pays Super Admin outside the app;
 * only Super Admin confirming receipt through this Action credits the wallet.
 */
class RecordStoreWalletTopup
{
    public function __construct(
        private readonly StoreWalletService $storeWallet,
        private readonly StoreActivityLogger $activityLog,
    ) {}

    public function __invoke(StoreWallet $wallet, float $amount, User $operator, ?string $description = null): StoreWalletLedgerEntry
    {
        $entry = $this->storeWallet->topUp($wallet, $amount, $operator, $description);

        $store = $wallet->store()->firstOrFail();
        $this->activityLog->record($store, $operator, 'wallet_topup', null, $entry, null, ['amount' => $amount]);

        return $entry;
    }
}

<?php

namespace App\Services;

use App\Models\StoreWallet;
use App\Models\StoreWalletLedgerEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * ARCHITECTURE.md: the ONLY class allowed to write `store_wallet_ledger_entries`
 * or mutate `store_wallets.balance` — mirrors `WalletLedgerService`'s
 * exclusivity rule for the member wallet (DOMAIN_LOGIC.md §16.1/§12).
 */
class StoreWalletService
{
    /**
     * Initial advance credit at store opening (DOMAIN_LOGIC.md §16.1) — the
     * company-held advance amount becomes the store's available payment
     * balance.
     */
    public function advanceCredit(StoreWallet $wallet, float $amount, User $operator, ?string $description = null): StoreWalletLedgerEntry
    {
        return $this->credit($wallet, 'advance_credit', $amount, $operator, $description ?? 'Initial jewellery allocation advance');
    }

    /**
     * Super Admin-confirmed top-up (DOMAIN_LOGIC.md §16.1) — the Store Owner
     * pays Super Admin outside the app; only this call credits the wallet.
     */
    public function topUp(StoreWallet $wallet, float $amount, User $operator, ?string $description = null): StoreWalletLedgerEntry
    {
        return $this->credit($wallet, 'topup', $amount, $operator, $description ?? 'Store Wallet top-up');
    }

    private function credit(StoreWallet $wallet, string $type, float $amount, User $operator, string $description): StoreWalletLedgerEntry
    {
        return DB::transaction(function () use ($wallet, $type, $amount, $operator, $description) {
            $locked = StoreWallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            $entry = StoreWalletLedgerEntry::create([
                'store_wallet_id' => $locked->id,
                'type' => $type,
                'amount' => $amount,
                'reference' => (string) Str::uuid(),
                'operator_user_id' => $operator->id,
                'description' => $description,
                'occurred_at' => now(),
            ]);

            $locked->increment('balance', $amount);

            return $entry;
        });
    }

    /**
     * Store-initiated member payment (new joining or repurchase paid from
     * the Store Wallet, DOMAIN_LOGIC.md §16.1) — checks sufficient balance
     * before confirming and blocks the transaction if insufficient, atomic
     * with whatever record the caller creates in the same DB transaction.
     */
    public function deduct(StoreWallet $wallet, float $amount, User $operator, string $description): StoreWalletLedgerEntry
    {
        return DB::transaction(function () use ($wallet, $amount, $operator, $description) {
            $locked = StoreWallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            if ($amount > (float) $locked->balance) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient Store Wallet balance for this transaction.',
                ]);
            }

            $entry = StoreWalletLedgerEntry::create([
                'store_wallet_id' => $locked->id,
                'type' => 'deduction',
                'amount' => $amount,
                'reference' => (string) Str::uuid(),
                'operator_user_id' => $operator->id,
                'description' => $description,
                'occurred_at' => now(),
            ]);

            $locked->decrement('balance', $amount);

            return $entry;
        });
    }
}

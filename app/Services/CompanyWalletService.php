<?php

namespace App\Services;

use App\Models\CompanyWallet;
use App\Models\CompanyWalletLedgerEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §12.2(b) — T-153. The ONLY class allowed to write
 * `company_wallet_ledger_entries` or mutate `company_wallets.balance`,
 * mirroring `WalletLedgerService`/`StoreWalletService`'s exclusivity rule.
 *
 * T-163 (28-09-2026) — Super Admin can top it up by hand, and the balance
 * can never go below zero: `debit()` refuses (with a validation alert) any
 * spend larger than the balance. Nothing spends from it yet; `debit()` is
 * the one guarded way any future spend must go through.
 */
class CompanyWalletService
{
    public function topUp(float $amount, User $operator, ?string $note): CompanyWalletLedgerEntry
    {
        $description = "Manual top-up by {$operator->name}".($note ? " — {$note}" : '');

        return $this->credit('manual_topup', $amount, $operator, $description);
    }

    public function debit(string $category, float $amount, ?Model $source, string $description): CompanyWalletLedgerEntry
    {
        return DB::transaction(function () use ($category, $amount, $source, $description) {
            $wallet = $this->wallet();
            $locked = CompanyWallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            if ((float) $locked->balance < $amount) {
                throw ValidationException::withMessages([
                    'amount' => 'Company Wallet balance (₹'.number_format((float) $locked->balance, 2).') is not enough for ₹'.number_format($amount, 2).'. Top up the Company Wallet first.',
                ]);
            }

            $entry = CompanyWalletLedgerEntry::create([
                'company_wallet_id' => $locked->id,
                'entry_type' => 'debit',
                'category' => $category,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'amount' => $amount,
                'description' => $description,
                'occurred_at' => now(),
            ]);

            $locked->decrement('balance', $amount);

            return $entry;
        });
    }

    /** The single company wallet row, created on first use. */
    public function wallet(): CompanyWallet
    {
        return CompanyWallet::firstOrCreate([], ['balance' => 0]);
    }

    public function credit(string $category, float $amount, ?Model $source, string $description): CompanyWalletLedgerEntry
    {
        return DB::transaction(function () use ($category, $amount, $source, $description) {
            $wallet = $this->wallet();
            $locked = CompanyWallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            $entry = CompanyWalletLedgerEntry::create([
                'company_wallet_id' => $locked->id,
                'entry_type' => 'credit',
                'category' => $category,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'amount' => $amount,
                'description' => $description,
                'occurred_at' => now(),
            ]);

            $locked->increment('balance', $amount);

            return $entry;
        });
    }
}

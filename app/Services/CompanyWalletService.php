<?php

namespace App\Services;

use App\Models\CompanyWallet;
use App\Models\CompanyWalletLedgerEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * DOMAIN_LOGIC.md §12.2(b) — T-153. The ONLY class allowed to write
 * `company_wallet_ledger_entries` or mutate `company_wallets.balance`,
 * mirroring `WalletLedgerService`/`StoreWalletService`'s exclusivity rule.
 * Credit-only for now — nothing in this project's confirmed scope spends
 * out of the Company Wallet yet.
 */
class CompanyWalletService
{
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

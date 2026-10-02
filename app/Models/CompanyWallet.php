<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DOMAIN_LOGIC.md §12.2(b) — T-153. A true singleton — always exactly one
 * row, created lazily by `CompanyWalletService`.
 *
 * @property-read Collection<int, CompanyWalletLedgerEntry> $ledgerEntries
 */
class CompanyWallet extends Model
{
    protected $fillable = ['balance'];

    protected function casts(): array
    {
        return ['balance' => 'decimal:2'];
    }

    /** @return HasMany<CompanyWalletLedgerEntry, $this> */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CompanyWalletLedgerEntry::class);
    }
}

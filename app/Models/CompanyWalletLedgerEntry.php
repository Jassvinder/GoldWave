<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DOMAIN_LOGIC.md §12.2(b) — T-153. The audit source of truth behind
 * `company_wallets.balance`, mirroring `WalletLedgerEntry`/
 * `StoreWalletLedgerEntry`'s exclusivity pattern.
 *
 * @property-read CompanyWallet|null $companyWallet
 */
class CompanyWalletLedgerEntry extends Model
{
    protected $fillable = [
        'company_wallet_id',
        'entry_type',
        'category',
        'source_type',
        'source_id',
        'amount',
        'description',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CompanyWallet, $this> */
    public function companyWallet(): BelongsTo
    {
        return $this->belongsTo(CompanyWallet::class);
    }
}

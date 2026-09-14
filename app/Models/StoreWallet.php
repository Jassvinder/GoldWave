<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property-read Store|null $store */
class StoreWallet extends Model
{
    protected $fillable = [
        'store_id',
        'balance',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return HasMany<StoreWalletLedgerEntry, $this> */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(StoreWalletLedgerEntry::class);
    }
}

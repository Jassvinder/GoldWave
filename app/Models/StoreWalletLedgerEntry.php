<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read StoreWallet|null $storeWallet
 * @property-read User|null $operator
 */
class StoreWalletLedgerEntry extends Model
{
    protected $fillable = [
        'store_wallet_id',
        'type',
        'amount',
        'reference',
        'operator_user_id',
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

    /** @return BelongsTo<StoreWallet, $this> */
    public function storeWallet(): BelongsTo
    {
        return $this->belongsTo(StoreWallet::class);
    }

    /** @return BelongsTo<User, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_user_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * DOMAIN_LOGIC.md §16.1 — a Store is owned by a `users` row (the Store
 * Owner), who §21 confirms is also always a full network `Member` in their
 * own right; that Member row is resolved via `owner()->member` when a
 * compensation Action needs to credit the owner's 2% share (§16.4).
 *
 * @property-read User|null $owner
 * @property-read StoreWallet|null $wallet
 * @property-read Collection<int, StoreInventoryItem> $inventoryItems
 */
class Store extends Model
{
    protected $fillable = [
        'name',
        'owner_user_id',
        'contact',
        'location',
        'status',
        'jewellery_allocation_value',
        'advance_amount',
    ];

    protected function casts(): array
    {
        return [
            'jewellery_allocation_value' => 'decimal:2',
            'advance_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return HasOne<StoreWallet, $this> */
    public function wallet(): HasOne
    {
        return $this->hasOne(StoreWallet::class);
    }

    /** @return HasMany<StoreInventoryItem, $this> */
    public function inventoryItems(): HasMany
    {
        return $this->hasMany(StoreInventoryItem::class);
    }

    /** @return HasMany<StoreSale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(StoreSale::class);
    }

    /** @return HasMany<StoreBuyback, $this> */
    public function buybacks(): HasMany
    {
        return $this->hasMany(StoreBuyback::class);
    }

    /** The Store Owner's own network Member identity (DOMAIN_LOGIC.md §21). */
    public function ownerMember(): ?Member
    {
        return $this->owner?->member;
    }
}

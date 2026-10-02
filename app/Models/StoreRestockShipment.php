<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DOMAIN_LOGIC.md §16.12 — T-152. A restock owed to a store that delivered a
 * member's plan jewellery without ever receiving the money for it, tracked
 * through `owed` → `sent` → `received`.
 *
 * @property-read Store|null $store
 * @property-read ProductBenefit|null $productBenefit
 * @property-read User|null $sentBy
 * @property-read User|null $receivedBy
 * @property-read StoreInventoryItem|null $resultingInventoryItem
 */
class StoreRestockShipment extends Model
{
    protected $fillable = [
        'store_id',
        'product_benefit_id',
        'item_name',
        'metal',
        'weight',
        'value',
        'status',
        'sent_at',
        'sent_by',
        'received_at',
        'received_by',
        'resulting_inventory_item_id',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:3',
            'value' => 'decimal:2',
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<ProductBenefit, $this> */
    public function productBenefit(): BelongsTo
    {
        return $this->belongsTo(ProductBenefit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /** @return BelongsTo<User, $this> */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return BelongsTo<StoreInventoryItem, $this> */
    public function resultingInventoryItem(): BelongsTo
    {
        return $this->belongsTo(StoreInventoryItem::class, 'resulting_inventory_item_id');
    }
}

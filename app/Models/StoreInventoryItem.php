<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** DOMAIN_LOGIC.md §16.5 — item-wise store stock. @property-read Store|null $store */
class StoreInventoryItem extends Model
{
    protected $fillable = [
        'store_id',
        'item_name',
        'metal',
        'weight',
        'quantity',
        'price',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:3',
            'price' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}

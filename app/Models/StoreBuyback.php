<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DOMAIN_LOGIC.md §16.7/§16.9 — the store buying an item back from the
 * member who owns it, at a versioned percentage of the item's current market
 * rate. Deliberately never linked to `store_profit_distributions` or
 * `income_ledger_calculations` — a Buyback is the reverse of a sale and is
 * outside both rules' scope.
 *
 * @property-read Store|null $store
 * @property-read Member|null $member
 * @property-read MetalRate|null $metalRate
 * @property-read StoreInventoryItem|null $resultingInventoryItem
 * @property-read RuleVersion|null $ruleVersion
 */
class StoreBuyback extends Model
{
    protected $fillable = [
        'store_id',
        'member_id',
        'item_name',
        'metal',
        'weight',
        'quantity',
        'description',
        'metal_rate_id',
        'rate_per_gram_at_buyback',
        'buyback_percent',
        'price_paid',
        'resulting_inventory_item_id',
        'rule_version_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:3',
            'rate_per_gram_at_buyback' => 'decimal:2',
            'buyback_percent' => 'decimal:3',
            'price_paid' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<MetalRate, $this> */
    public function metalRate(): BelongsTo
    {
        return $this->belongsTo(MetalRate::class);
    }

    /** @return BelongsTo<StoreInventoryItem, $this> */
    public function resultingInventoryItem(): BelongsTo
    {
        return $this->belongsTo(StoreInventoryItem::class, 'resulting_inventory_item_id');
    }

    /** @return BelongsTo<RuleVersion, $this> */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class);
    }
}

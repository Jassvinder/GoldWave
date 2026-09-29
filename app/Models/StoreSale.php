<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * DOMAIN_LOGIC.md §15/§16.2/§16.4 — one row per store transaction (New Sale /
 * Purchase / Repurchase). §16.8: `store_inventory_item_id`+`quantity` are
 * what a confirmed sale decrements; a Buyback (the reverse direction) is a
 * separate `StoreBuyback` row entirely, never a `StoreSale` (§16.9).
 *
 * @property-read Store|null $store
 * @property-read Member|null $member
 * @property-read StoreInventoryItem|null $inventoryItem
 * @property-read Invoice|null $invoice
 */
class StoreSale extends Model
{
    protected $fillable = [
        'store_id',
        'member_id',
        'store_inventory_item_id',
        'transaction_type',
        'item_name',
        'metal',
        'item_weight',
        'quantity',
        'rate',
        'metal_rate_id',
        'metal_value',
        'making_charge_percent',
        'making_charges',
        'hallmark_charges',
        'gst_percent',
        'sale_amount',
        'gst_amount',
        'total_invoice_amount',
        'payment_source',
        'store_wallet_deduction_id',
        'distribution_status',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'item_weight' => 'decimal:3',
            'rate' => 'decimal:2',
            'metal_value' => 'decimal:2',
            'making_charge_percent' => 'decimal:2',
            'making_charges' => 'decimal:2',
            'hallmark_charges' => 'decimal:2',
            'gst_percent' => 'decimal:2',
            'sale_amount' => 'decimal:2',
            'gst_amount' => 'decimal:2',
            'total_invoice_amount' => 'decimal:2',
        ];
    }

    /**
     * T-169 (28-09-2026, user decision) — Store Profit Distribution and Purchase/Repurchase income are
     * calculated on the metal value only (not making, hallmark or GST). A sale recorded before automatic
     * pricing has no `metal_value`, so its typed `sale_amount` stays its base.
     */
    public function incomeBase(): float
    {
        return (float) ($this->metal_value ?? $this->sale_amount);
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

    /** @return BelongsTo<StoreInventoryItem, $this> */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(StoreInventoryItem::class, 'store_inventory_item_id');
    }

    /** @return BelongsTo<StoreWalletLedgerEntry, $this> */
    public function storeWalletDeduction(): BelongsTo
    {
        return $this->belongsTo(StoreWalletLedgerEntry::class, 'store_wallet_deduction_id');
    }

    /** @return HasOne<Invoice, $this> */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /** @return HasMany<HallmarkEntry, $this> */
    public function hallmarks(): HasMany
    {
        return $this->hasMany(HallmarkEntry::class)->orderBy('piece_no');
    }

    /** @return HasMany<StoreProfitDistribution, $this> */
    public function profitDistributions(): HasMany
    {
        return $this->hasMany(StoreProfitDistribution::class);
    }
}

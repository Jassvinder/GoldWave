<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * T-171 (28-09-2026) — the company's own plan-jewellery handover, entered by Super Admin (no stock, no income), priced
 * like a purchase and billed (DOMAIN_LOGIC.md §16.2 T-171 note).
 *
 * @property-read Member $member
 * @property-read ProductBenefit $productBenefit
 * @property-read Invoice|null $invoice
 * @property-read User $deliveredBy
 */
class CompanyDelivery extends Model
{
    protected $fillable = [
        'product_benefit_id',
        'member_id',
        'item_name',
        'metal',
        'item_weight',
        'quantity',
        'metal_rate_id',
        'rate',
        'metal_value',
        'making_charge_percent',
        'making_charges',
        'hallmark_charges',
        'sale_amount',
        'gst_percent',
        'gst_amount',
        'total_invoice_amount',
        'delivered_by',
        'delivered_at',
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
            'sale_amount' => 'decimal:2',
            'gst_percent' => 'decimal:2',
            'gst_amount' => 'decimal:2',
            'total_invoice_amount' => 'decimal:2',
            'delivered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<ProductBenefit, $this> */
    public function productBenefit(): BelongsTo
    {
        return $this->belongsTo(ProductBenefit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
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
}

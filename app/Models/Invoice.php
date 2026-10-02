<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DOMAIN_LOGIC.md §16.2 — a bill for a store sale or (T-171) a company delivery, created when it is first generated;
 * every later copy is a duplicate with the same number.
 *
 * @property-read StoreSale|null $storeSale
 * @property-read CompanyDelivery|null $companyDelivery
 */
class Invoice extends Model
{
    protected $fillable = [
        'store_sale_id',
        'company_delivery_id',
        'invoice_no',
        'generated_at',
        'pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<StoreSale, $this> */
    public function storeSale(): BelongsTo
    {
        return $this->belongsTo(StoreSale::class);
    }

    /** @return BelongsTo<CompanyDelivery, $this> */
    public function companyDelivery(): BelongsTo
    {
        return $this->belongsTo(CompanyDelivery::class);
    }
}

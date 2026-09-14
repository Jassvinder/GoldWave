<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** DOMAIN_LOGIC.md §16.2 — one invoice per confirmed store sale. @property-read StoreSale|null $storeSale */
class Invoice extends Model
{
    protected $fillable = [
        'store_sale_id',
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
}

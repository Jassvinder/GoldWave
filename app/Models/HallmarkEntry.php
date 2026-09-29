<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** T-171 (28-09-2026) — one hallmarked piece on a bill: its HUID number and hallmark charge (DOMAIN_LOGIC.md §16.2). */
class HallmarkEntry extends Model
{
    protected $fillable = [
        'store_sale_id',
        'company_delivery_id',
        'piece_no',
        'huid',
        'charge',
    ];

    protected function casts(): array
    {
        return [
            'charge' => 'decimal:2',
        ];
    }
}

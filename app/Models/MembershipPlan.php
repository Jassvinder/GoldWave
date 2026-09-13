<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipPlan extends Model
{
    protected $fillable = [
        'code',
        'name',
        'amount',
        'installment_count',
        'product_category',
        'fixed_weight_grams',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fixed_weight_grams' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    /** DOMAIN_LOGIC.md §3: null installment_count means a one-time plan (E/F), not an EMI plan. */
    public function isEmiPlan(): bool
    {
        return $this->installment_count !== null;
    }
}

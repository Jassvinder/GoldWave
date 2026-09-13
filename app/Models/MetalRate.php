<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read User|null $createdBy
 */
class MetalRate extends Model
{
    protected $fillable = [
        'metal',
        'rate_per_gram',
        'effective_from',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rate_per_gram' => 'decimal:2',
            'effective_from' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

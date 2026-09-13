<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Collection<int, RuleValue> $values
 * @property-read User|null $publishedBy
 */
class RuleVersion extends Model
{
    protected $fillable = [
        'version_no',
        'effective_from',
        'effective_to',
        'is_active',
        'published_by',
        'published_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /** @return HasMany<RuleValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(RuleValue::class);
    }

    /** @return BelongsTo<User, $this> */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}

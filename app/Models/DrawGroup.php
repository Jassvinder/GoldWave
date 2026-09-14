<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Collection<int, DrawGroupMember> $members
 * @property-read Collection<int, DrawGroupMonthConfig> $monthConfigs
 * @property-read Collection<int, DrawExecution> $executions
 */
class DrawGroup extends Model
{
    protected $fillable = [
        'group_no',
        'size',
        'cycle_started_month',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'cycle_started_month' => 'date',
        ];
    }

    /** @return HasMany<DrawGroupMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(DrawGroupMember::class);
    }

    /** @return HasMany<DrawGroupMonthConfig, $this> */
    public function monthConfigs(): HasMany
    {
        return $this->hasMany(DrawGroupMonthConfig::class);
    }

    /** @return HasMany<DrawExecution, $this> */
    public function executions(): HasMany
    {
        return $this->hasMany(DrawExecution::class);
    }
}

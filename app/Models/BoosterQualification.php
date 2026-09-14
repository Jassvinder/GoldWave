<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Member $member
 * @property-read RuleVersion $ruleVersion
 * @property-read Collection<int, BoosterPayoutSchedule> $payoutSchedules
 */
class BoosterQualification extends Model
{
    protected $fillable = [
        'member_id',
        'level_no',
        'qualified_at',
        'rule_version_id',
    ];

    protected function casts(): array
    {
        return [
            'qualified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<RuleVersion, $this> */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class);
    }

    /** @return HasMany<BoosterPayoutSchedule, $this> */
    public function payoutSchedules(): HasMany
    {
        return $this->hasMany(BoosterPayoutSchedule::class);
    }
}

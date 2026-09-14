<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read DrawGroup $drawGroup
 * @property-read Member $winner
 * @property-read Member|null $uplineBenefitMember
 * @property-read User|null $reconciledBy
 * @property-read Collection<int, DrawExecutionCorrection> $corrections
 */
class DrawExecution extends Model
{
    protected $fillable = [
        'draw_group_id',
        'cycle_month_no',
        'executed_at',
        'winner_member_id',
        'rng_proof',
        'upline_benefit_member_id',
        'status',
        'reconciled_at',
        'reconciled_by',
    ];

    protected function casts(): array
    {
        return [
            'executed_at' => 'datetime',
            'reconciled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<DrawGroup, $this> */
    public function drawGroup(): BelongsTo
    {
        return $this->belongsTo(DrawGroup::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'winner_member_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function uplineBenefitMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'upline_benefit_member_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    /** @return HasMany<DrawExecutionCorrection, $this> */
    public function corrections(): HasMany
    {
        return $this->hasMany(DrawExecutionCorrection::class);
    }
}

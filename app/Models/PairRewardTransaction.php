<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DOMAIN_LOGIC.md §7.2 — one row per milestone a member ever crosses,
 * permanent and never re-created (`unique(member_id, milestone_no)`).
 *
 * @property-read Member $member
 * @property-read RuleVersion $ruleVersion
 */
class PairRewardTransaction extends Model
{
    protected $fillable = [
        'member_id',
        'milestone_no',
        'left_consumed_count',
        'right_consumed_count',
        'reward_amount',
        'rule_version_id',
        'calculated_for_month',
    ];

    protected function casts(): array
    {
        return [
            'reward_amount' => 'decimal:2',
            'calculated_for_month' => 'date',
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
}

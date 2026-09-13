<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read Member|null $member
 * @property-read MembershipPlan|null $membershipPlan
 * @property-read MetalRate|null $metalRate
 */
class ProductBenefit extends Model
{
    protected $fillable = [
        'member_id',
        'membership_plan_id',
        'metal',
        'metal_rate_id',
        'rate_per_gram_at_entry',
        'entry_date',
    ];

    protected function casts(): array
    {
        return [
            'rate_per_gram_at_entry' => 'decimal:2',
            'entry_date' => 'date',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<MembershipPlan, $this> */
    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    /** @return BelongsTo<MetalRate, $this> */
    public function metalRate(): BelongsTo
    {
        return $this->belongsTo(MetalRate::class);
    }
}

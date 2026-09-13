<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Member|null $member
 * @property-read MembershipPlan|null $membershipPlan
 * @property-read MetalRate|null $metalRate
 * @property-read RuleVersion|null $ruleVersion
 * @property-read Collection<int, EmiInstallment> $installments
 */
class EmiSchedule extends Model
{
    protected $fillable = [
        'member_id',
        'membership_plan_id',
        'total_installments',
        'rate_booking_method',
        'installment_amount',
        'metal_rate_id',
        'rate_per_gram_at_booking',
        'fixed_weight_grams',
        'maintenance_cost',
        'rule_version_id',
        'future_commitment_amount',
    ];

    protected function casts(): array
    {
        return [
            'installment_amount' => 'decimal:2',
            'rate_per_gram_at_booking' => 'decimal:2',
            'fixed_weight_grams' => 'decimal:3',
            'maintenance_cost' => 'decimal:2',
            'future_commitment_amount' => 'decimal:2',
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

    /** @return BelongsTo<RuleVersion, $this> */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class);
    }

    /** @return HasMany<EmiInstallment, $this> */
    public function installments(): HasMany
    {
        return $this->hasMany(EmiInstallment::class);
    }
}

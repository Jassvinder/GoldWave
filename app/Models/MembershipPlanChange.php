<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * T-237 (DOMAIN_LOGIC.md §3.3) — one create or edit made on the Super Admin Membership Plans page.
 *
 * @property array<string, mixed>|null $before
 * @property array<string, mixed> $after
 * @property-read MembershipPlan $membershipPlan
 * @property-read User $changedBy
 */
class MembershipPlanChange extends Model
{
    protected $fillable = [
        'membership_plan_id',
        'changed_by',
        'action',
        'before',
        'after',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }

    /** @return BelongsTo<MembershipPlan, $this> */
    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

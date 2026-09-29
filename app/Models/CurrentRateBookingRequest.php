<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * T-166 (28-09-2026) — a member's request to book their EMI plan at the
 * Current Rate, approved or cancelled by Super Admin (DOMAIN_LOGIC.md §3.0).
 *
 * @property array<string, mixed>|null $estimate
 * @property-read Member $member
 * @property-read EmiSchedule $emiSchedule
 * @property-read User|null $decidedBy
 */
class CurrentRateBookingRequest extends Model
{
    protected $fillable = [
        'emi_schedule_id',
        'member_id',
        'status',
        'estimate',
        'decided_by',
        'decided_at',
        'cancel_message',
    ];

    protected function casts(): array
    {
        return [
            'estimate' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<EmiSchedule, $this> */
    public function emiSchedule(): BelongsTo
    {
        return $this->belongsTo(EmiSchedule::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}

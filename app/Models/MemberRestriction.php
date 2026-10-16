<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * T-231 (DOMAIN_LOGIC.md §2.3) — one Payout Hold or Account Block period: who started it and why, and, once lifted,
 * who ended it and why. The member's current state is on `members.payout_hold_at` / `members.blocked_at`.
 *
 * @property-read Member $member
 * @property-read User $startedBy
 * @property-read User|null $endedBy
 */
class MemberRestriction extends Model
{
    public const PAYOUT_HOLD = 'payout_hold';

    public const ACCOUNT_BLOCK = 'account_block';

    protected $fillable = [
        'member_id',
        'type',
        'reason',
        'started_at',
        'started_by',
        'ended_at',
        'ended_by',
        'end_reason',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<User, $this> */
    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /** @return BelongsTo<User, $this> */
    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    /** The members.* column that holds this restriction's current state. */
    public static function column(string $type): string
    {
        return $type === self::ACCOUNT_BLOCK ? 'blocked_at' : 'payout_hold_at';
    }
}

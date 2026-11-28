<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * T-252 (DOMAIN_LOGIC.md §3.5) — one Super Admin change of a not-yet-active member's sponsor and/or tree place.
 *
 * @property-read Member $member
 * @property-read Member|null $oldSponsor
 * @property-read Member|null $newSponsor
 * @property-read Member|null $oldParent
 * @property-read Member|null $newParent
 * @property-read User $changedBy
 */
class MemberPlacementChange extends Model
{
    protected $fillable = [
        'member_id',
        'old_sponsor_id',
        'new_sponsor_id',
        'old_parent_id',
        'old_side',
        'new_parent_id',
        'new_side',
        'reason',
        'changed_by',
    ];

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function oldSponsor(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'old_sponsor_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function newSponsor(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'new_sponsor_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function oldParent(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'old_parent_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function newParent(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'new_parent_id');
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

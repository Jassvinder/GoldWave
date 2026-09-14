<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read DrawGroup $drawGroup
 * @property-read Member $member
 */
class DrawGroupMember extends Model
{
    protected $fillable = [
        'draw_group_id',
        'member_id',
        'is_winner_removed',
    ];

    protected function casts(): array
    {
        return [
            'is_winner_removed' => 'boolean',
        ];
    }

    /** @return BelongsTo<DrawGroup, $this> */
    public function drawGroup(): BelongsTo
    {
        return $this->belongsTo(DrawGroup::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}

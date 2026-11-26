<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * T-248 (DOMAIN_LOGIC.md §8.8) — one draw winner shown on the member login banner. Display only; it never touches the
 * draw engine, wallet or payouts. Name and Customer ID are read from the linked member.
 *
 * @property int $group_no
 * @property Carbon $draw_month
 * @property int $member_id
 * @property string $prize
 * @property-read Member $member
 */
class BannerWinner extends Model
{
    protected $fillable = [
        'group_no',
        'draw_month',
        'member_id',
        'prize',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'group_no' => 'integer',
            'draw_month' => 'date',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

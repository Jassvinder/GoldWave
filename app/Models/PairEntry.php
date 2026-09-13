<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DOMAIN_LOGIC.md §7 — one row per (Binary Position ancestor, qualifying
 * joining) pair. `member_id` is the beneficiary (the ancestor accumulating
 * Left/Right team-size business), never the newly-joined member themselves
 * unless they happen to also be an ancestor of someone else.
 *
 * @property-read Member $member
 * @property-read Payment $sourcePayment
 */
class PairEntry extends Model
{
    protected $fillable = [
        'member_id',
        'side',
        'source_payment_id',
        'status',
        'consumed_for_milestone_no',
    ];

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function sourcePayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'source_payment_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DOMAIN_LOGIC.md §3.0 — append-only log row for a Current Rate booking or a Super Admin revert of one.
 *
 * @property-read EmiSchedule $schedule
 * @property-read User|null $performedBy
 */
class EmiRateBookingEvent extends Model
{
    protected $fillable = [
        'emi_schedule_id',
        'event',
        'performed_by_user_id',
        'reason',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
        ];
    }

    /** @return BelongsTo<EmiSchedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(EmiSchedule::class, 'emi_schedule_id');
    }

    /** @return BelongsTo<User, $this> */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }
}

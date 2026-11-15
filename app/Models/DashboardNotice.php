<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * T-245 (DOMAIN_LOGIC.md §2.2b) — an offer, event or notice shown at the top of the member dashboard while it is active
 * and today is within its optional date range (both ends inclusive).
 *
 * @property string $kind
 * @property string $title
 * @property string|null $body
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property bool $is_active
 */
class DashboardNotice extends Model
{
    public const KINDS = ['offer', 'event', 'notice'];

    protected $fillable = [
        'kind',
        'title',
        'body',
        'starts_on',
        'ends_on',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Active and running today (Asia/Kolkata).
     *
     * @param  Builder<DashboardNotice>  $query
     * @return Builder<DashboardNotice>
     */
    public function scopeShowingToday(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', $today))
            ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today));
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

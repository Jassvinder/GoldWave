<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * T-018 — one row per queued report export request (`DOMAIN_LOGIC.md` §21).
 *
 * @property-read User $requestedBy
 */
class ReportExport extends Model
{
    protected $fillable = [
        'requested_by',
        'report_type',
        'format',
        'filters',
        'status',
        'file_path',
        'row_count',
        'error_message',
        'requested_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}

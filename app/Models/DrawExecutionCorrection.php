<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DOMAIN_LOGIC.md §8.6's "reversal/correction audit record" — append-only,
 * never edited or deleted; `DrawExecution`'s own result fields are never
 * touched by a correction (T-017 pre-coding pass).
 *
 * @property-read DrawExecution $drawExecution
 * @property-read User $createdBy
 */
class DrawExecutionCorrection extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'draw_execution_id',
        'note',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<DrawExecution, $this> */
    public function drawExecution(): BelongsTo
    {
        return $this->belongsTo(DrawExecution::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

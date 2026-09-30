<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $prize_value decimal:2 cast — e.g. "20000.00"
 * @property-read DrawGroup $drawGroup
 */
class DrawGroupMonthConfig extends Model
{
    protected $fillable = [
        'draw_group_id',
        'cycle_month_no',
        'prize_name',
        'prize_value',
        'metal_type',
        'winners_count',
    ];

    protected function casts(): array
    {
        return [
            'prize_value' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<DrawGroup, $this> */
    public function drawGroup(): BelongsTo
    {
        return $this->belongsTo(DrawGroup::class);
    }
}

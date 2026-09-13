<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read RuleVersion|null $ruleVersion
 */
class RuleValue extends Model
{
    protected $fillable = [
        'rule_version_id',
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /** @return BelongsTo<RuleVersion, $this> */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class);
    }
}

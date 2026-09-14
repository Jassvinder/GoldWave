<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * DOMAIN_LOGIC.md §16.3 — one row per store-scoped action, written by the
 * Action that performs it (this task); the filterable Super Admin viewing
 * page is later Admin/Super Admin page work (T-016/T-017), not part of this
 * model's own scope.
 *
 * @property-read Store|null $store
 * @property-read User|null $operator
 * @property-read Member|null $affectedMember
 */
class StoreActivityLog extends Model
{
    protected $fillable = [
        'store_id',
        'operator_user_id',
        'action_type',
        'affected_member_id',
        'affected_reference_type',
        'affected_reference_id',
        'old_value',
        'new_value',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'old_value' => 'array',
            'new_value' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<User, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_user_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function affectedMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'affected_member_id');
    }

    /** @return MorphTo<Model, $this> */
    public function affectedReference(): MorphTo
    {
        return $this->morphTo();
    }
}

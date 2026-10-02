<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DOMAIN_LOGIC.md §16.13 (T-185) — a member's Repurchase on EMI: the Store Admin's request for one piece of their store's
 * stock over 10 or 20 Current Rate EMIs, Super Admin's decision, the resulting `store_repurchase` EMI schedule, and later
 * its break (silver owed) or delivery.
 *
 * Statuses: `pending` (awaiting Super Admin) → `cancelled`, or `active` (approved, EMIs running, piece held) →
 * `completed` (every EMI paid) → `delivered`; or `active` → `broken` (3 overdue EMIs) → `delivered` (silver handed over).
 *
 * @property array<string, mixed>|null $estimate
 * @property-read Member $member
 * @property-read Store $store
 * @property-read StoreInventoryItem $inventoryItem
 * @property-read User $requestedBy
 * @property-read User|null $decidedBy
 * @property-read EmiSchedule|null $emiSchedule
 */
class StoreEmiBooking extends Model
{
    public const INSTALLMENT_COUNTS = [10, 20];

    protected $fillable = [
        'member_id',
        'store_id',
        'store_inventory_item_id',
        'requested_by',
        'item_name',
        'metal',
        'weight_grams',
        'installment_count',
        'status',
        'estimate',
        'decided_by',
        'decided_at',
        'cancel_message',
        'emi_schedule_id',
        'broken_at',
        'principal_paid',
        'silver_metal_rate_id',
        'silver_rate_per_gram',
        'silver_grams_owed',
        'delivered_at',
        'delivered_by',
    ];

    protected function casts(): array
    {
        return [
            'weight_grams' => 'decimal:3',
            'installment_count' => 'integer',
            'estimate' => 'array',
            'decided_at' => 'datetime',
            'broken_at' => 'datetime',
            'principal_paid' => 'decimal:2',
            'silver_rate_per_gram' => 'decimal:2',
            'silver_grams_owed' => 'decimal:3',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * A member may have only one open booking at a time (DOMAIN_LOGIC.md §16.13 point 1): waiting, running, fully paid
     * but not handed over, or broken with silver still to hand over. A broken booking with nothing owed is closed.
     *
     * @param  Builder<StoreEmiBooking>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereIn('status', ['pending', 'active', 'completed'])
            ->orWhere(fn (Builder $broken) => $broken->where('status', 'broken')->where('silver_grams_owed', '>', 0)));
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<StoreInventoryItem, $this> */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(StoreInventoryItem::class, 'store_inventory_item_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return BelongsTo<EmiSchedule, $this> */
    public function emiSchedule(): BelongsTo
    {
        return $this->belongsTo(EmiSchedule::class);
    }
}

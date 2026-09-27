<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property-read Member|null $member
 * @property-read User|null $verifiedBy
 * @property-read EmiInstallment|null $emiInstallment
 * @property-read Store|null $payingStore
 * @property-read Member|null $payingMember
 */
class Payment extends Model
{
    protected $fillable = [
        'member_id',
        'paying_store_id',
        'paying_member_id',
        'type',
        'amount',
        'mode',
        'status',
        'provider_reference',
        'gateway_order_id',
        'gateway_payload',
        'idempotency_key',
        'cash_status',
        'verified_by',
        'verified_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gateway_payload' => 'array',
            'verified_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function payingStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'paying_store_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function payingMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'paying_member_id');
    }

    /** @return BelongsTo<User, $this> */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** @return HasOne<EmiInstallment, $this> */
    public function emiInstallment(): HasOne
    {
        return $this->hasOne(EmiInstallment::class);
    }
}

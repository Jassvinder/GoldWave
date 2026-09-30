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
        'covers_installments',
        'mode',
        'upi_reference',
        'upi_screenshot_path',
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
            'covers_installments' => 'integer',
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

    /** T-184 — what an EMI payment was for, in notifications: "EMI #5", or "all 16 remaining EMIs" for a full payment. */
    public function emiLabel(): string
    {
        $label = $this->covers_installments !== null
            ? "all {$this->covers_installments} remaining EMIs"
            : 'EMI #'.($this->emiInstallment->installment_no ?? '?');

        // T-185 — say when it is a store Repurchase on EMI rather than the plan.
        return $this->storeEmiBooking() !== null ? "{$label} of the Repurchase on EMI" : $label;
    }

    /** T-185 — the store Repurchase on EMI this EMI payment belongs to, if it is not the plan's own schedule. */
    public function storeEmiBooking(): ?StoreEmiBooking
    {
        if ($this->type !== 'emi_installment') {
            return null;
        }

        $schedule = $this->emiInstallment?->emiSchedule;

        return $schedule?->isStoreRepurchase() ? $schedule->storeEmiBooking : null;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * DOMAIN_LOGIC.md §3.4 (T-251) — one combined Cash / GPay-UPI payment that covers several IDs of one person: their
 * registrations, or one EMI each. The Ref ID, screenshot and approval state live here; every ID keeps its own
 * `payments` row (`payment_batch_id`), which is what the income engines read. Approved or rejected as one.
 *
 * @property int $id
 * @property string $type
 * @property string $mode
 * @property string $amount
 * @property string|null $upi_reference
 * @property string|null $upi_screenshot_path
 * @property string $status
 * @property string|null $cash_status
 * @property int|null $created_by
 * @property int|null $verified_by
 * @property Carbon|null $verified_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property-read Collection<int, Payment> $payments
 * @property-read User|null $createdBy
 */
class PaymentBatch extends Model
{
    protected $fillable = [
        'type',
        'mode',
        'amount',
        'upi_reference',
        'upi_screenshot_path',
        'status',
        'cash_status',
        'created_by',
        'verified_by',
        'verified_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'verified_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

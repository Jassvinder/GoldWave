<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read EmiSchedule|null $emiSchedule
 * @property-read Payment|null $payment
 */
class EmiInstallment extends Model
{
    protected $fillable = [
        'emi_schedule_id',
        'installment_no',
        'due_date',
        'amount',
        'status',
        'payment_id',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<EmiSchedule, $this> */
    public function emiSchedule(): BelongsTo
    {
        return $this->belongsTo(EmiSchedule::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}

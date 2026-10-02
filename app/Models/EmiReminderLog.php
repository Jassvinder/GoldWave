<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * T-142 — remembers which reminder kind was already sent for which installment, so `SendEmiReminders` never reminds twice.
 *
 * @property-read EmiInstallment $installment
 */
class EmiReminderLog extends Model
{
    protected $fillable = [
        'emi_installment_id',
        'kind',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EmiInstallment, $this> */
    public function installment(): BelongsTo
    {
        return $this->belongsTo(EmiInstallment::class, 'emi_installment_id');
    }
}

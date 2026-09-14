<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read BoosterQualification $boosterQualification
 * @property-read WalletLedgerEntry|null $walletLedgerEntry
 */
class BoosterPayoutSchedule extends Model
{
    protected $fillable = [
        'booster_qualification_id',
        'month_no',
        'scheduled_date',
        'amount',
        'status',
        'wallet_ledger_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<BoosterQualification, $this> */
    public function boosterQualification(): BelongsTo
    {
        return $this->belongsTo(BoosterQualification::class);
    }

    /** @return BelongsTo<WalletLedgerEntry, $this> */
    public function walletLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(WalletLedgerEntry::class);
    }
}

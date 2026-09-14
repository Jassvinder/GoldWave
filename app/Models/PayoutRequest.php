<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Member $member
 * @property-read MemberBankDetail|null $bankDetail
 * @property-read WalletLedgerEntry|null $holdLedgerEntry
 * @property-read Collection<int, PayoutTransaction> $transactions
 */
class PayoutRequest extends Model
{
    protected $fillable = [
        'member_id',
        'requested_amount',
        'member_bank_detail_id',
        'status',
        'hold_ledger_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<MemberBankDetail, $this> */
    public function bankDetail(): BelongsTo
    {
        return $this->belongsTo(MemberBankDetail::class, 'member_bank_detail_id');
    }

    /** @return BelongsTo<WalletLedgerEntry, $this> */
    public function holdLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(WalletLedgerEntry::class, 'hold_ledger_entry_id');
    }

    /** @return HasMany<PayoutTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(PayoutTransaction::class);
    }
}

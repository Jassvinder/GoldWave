<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property-read Member $member
 * @property-read Model|null $source
 */
class WalletLedgerEntry extends Model
{
    /** Stored status → what a payout hold shows the member (see statusLabel()). */
    public const PAYOUT_STATUS_LABELS = [
        'pending' => 'on hold',
        'confirmed' => 'paid',
        'reversed' => 'released',
    ];

    protected $fillable = [
        'member_id',
        'entry_type',
        'category',
        'source_type',
        'source_id',
        'amount',
        'status',
        'description',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * A payout hold's stored description ("Payout request #N hold") and raw
     * status never change wording once the hold is confirmed or released,
     * so a member couldn't tell that "confirmed" meant "paid to the bank".
     * Rewrites both for display only, from the linked payout request and
     * its transaction — the stored ledger row stays untouched (DOMAIN_LOGIC.md
     * §11.1). Callers should eager-load `source.transactions` for payout rows.
     */
    public function displayDescription(): ?string
    {
        $request = $this->payoutSource();

        if ($request === null) {
            return $this->description;
        }

        return match ($this->status) {
            'pending' => "Payout request #{$request->id} — on hold, awaiting processing",
            'confirmed' => $this->paidDescription($request),
            default => "Payout request #{$request->id} {$request->status} — amount released back to wallet",
        };
    }

    /** Member-facing status word — a payout hold reads On Hold / Paid / Released instead of pending / confirmed / reversed. */
    public function statusLabel(): string
    {
        if ($this->payoutSource() === null) {
            return $this->status;
        }

        return self::PAYOUT_STATUS_LABELS[$this->status];
    }

    private function payoutSource(): ?PayoutRequest
    {
        if ($this->category !== 'payout' || $this->source_type !== (new PayoutRequest)->getMorphClass()) {
            return null;
        }

        $source = $this->source;

        return $source instanceof PayoutRequest ? $source : null;
    }

    private function paidDescription(PayoutRequest $request): string
    {
        $transaction = $request->transactions->firstWhere('status', 'processed');

        if (! $transaction instanceof PayoutTransaction) {
            return "Payout #{$request->id} paid";
        }

        $description = "Payout #{$request->id} paid via {$transaction->methodLabel()}";

        $accountNumber = (string) ($transaction->beneficiary_snapshot['account_number'] ?? '');

        if ($accountNumber !== '') {
            $bankName = $transaction->beneficiary_snapshot['bank_name'] ?? 'bank';
            $description .= " to {$bankName} ••".substr($accountNumber, -4);
        }

        if ($transaction->reference) {
            $description .= " (ref {$transaction->reference})";
        }

        return $description;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array{account_holder_name?: string|null, account_number?: string|null, ifsc_code?: string|null, bank_name?: string|null} $beneficiary_snapshot
 * @property-read PayoutRequest $payoutRequest
 * @property-read User $processedBy
 */
class PayoutTransaction extends Model
{
    /** Display names for `method` — the same four options the Super Admin's Process Payout dialog offers. */
    public const METHOD_LABELS = [
        'bank_transfer' => 'Bank Transfer',
        'gpay_upi' => 'GPay / UPI',
        'cheque' => 'Cheque',
        'in_app_provider' => 'In-App Provider',
    ];

    protected $fillable = [
        'payout_request_id',
        'amount_snapshot',
        'beneficiary_snapshot',
        'method',
        'reference',
        'batch_reference',
        'tds_amount',
        'processing_fee',
        'status',
        'processed_by',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_snapshot' => 'decimal:2',
            'beneficiary_snapshot' => 'array',
            'tds_amount' => 'decimal:2',
            'processing_fee' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PayoutRequest, $this> */
    public function payoutRequest(): BelongsTo
    {
        return $this->belongsTo(PayoutRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Net amount actually transferred to the beneficiary after TDS and any
     * processing fee are deducted from the requested amount (DOMAIN_LOGIC.md
     * §11.2, TEST.md scenario 7).
     */
    public function netAmount(): float
    {
        return (float) $this->amount_snapshot - (float) $this->tds_amount - (float) $this->processing_fee;
    }

    public function methodLabel(): string
    {
        return self::METHOD_LABELS[$this->method];
    }
}

<?php

namespace App\Actions\Payout;

use App\Models\PayoutRequest;
use App\Services\WalletLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §11.2 point 1 — a pending payout request withdrawn
 * without a rejection judgement (e.g. an accidental duplicate submission).
 * Same wallet effect as RejectPayoutRequest — only the resulting status
 * differs (DOMAIN_LOGIC.md §21 T-009 pre-coding pass).
 */
class CancelPayoutRequest
{
    public function __construct(
        private readonly WalletLedgerService $wallet,
    ) {}

    public function __invoke(PayoutRequest $payoutRequest): PayoutRequest
    {
        return DB::transaction(function () use ($payoutRequest) {
            $locked = PayoutRequest::whereKey($payoutRequest->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'payout_request' => 'Only a pending payout request can be cancelled.',
                ]);
            }

            $locked->update(['status' => 'cancelled']);

            $hold = $locked->holdLedgerEntry()->firstOrFail();
            $this->wallet->releaseHold($hold);

            return $locked->fresh();
        });
    }
}

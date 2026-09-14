<?php

namespace App\Actions\Payout;

use App\Models\PayoutRequest;
use App\Services\WalletLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §11.2 point 1 — Super Admin declines a pending payout
 * request (e.g. ineligible/suspicious). Releases the wallet hold; no
 * `payout_transactions` row is created since no payment was ever attempted.
 */
class RejectPayoutRequest
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
                    'payout_request' => 'Only a pending payout request can be rejected.',
                ]);
            }

            $locked->update(['status' => 'rejected']);

            $hold = $locked->holdLedgerEntry()->firstOrFail();
            $this->wallet->releaseHold($hold);

            return $locked->fresh();
        });
    }
}

<?php

namespace App\Actions\Payout;

use App\Models\PayoutRequest;
use App\Models\PayoutTransaction;
use App\Models\User;
use App\Services\WalletLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §11.2 point 7 — when a payout attempt fails (e.g. a
 * bounced bank transfer), a `payout_transactions` row is still created for
 * the audit trail (status=failed), but the wallet hold is released rather
 * than confirmed — the member's balance must stay consistent and a failed
 * payout must never create a permanent debit.
 */
class FailPayoutRequest
{
    public function __construct(
        private readonly WalletLedgerService $wallet,
    ) {}

    public function __invoke(
        PayoutRequest $payoutRequest,
        User $operator,
        string $method,
        ?string $reference = null,
        ?string $batchReference = null,
    ): PayoutTransaction {
        return DB::transaction(function () use ($payoutRequest, $operator, $method, $reference, $batchReference) {
            $locked = PayoutRequest::whereKey($payoutRequest->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'payout_request' => 'Only a pending payout request can be marked failed.',
                ]);
            }

            $bankDetail = $locked->bankDetail()->firstOrFail();

            $transaction = PayoutTransaction::create([
                'payout_request_id' => $locked->id,
                'amount_snapshot' => $locked->requested_amount,
                'beneficiary_snapshot' => [
                    'account_holder_name' => $bankDetail->account_holder_name,
                    'account_number' => $bankDetail->account_number,
                    'ifsc_code' => $bankDetail->ifsc_code,
                    'bank_name' => $bankDetail->bank_name,
                ],
                'method' => $method,
                'reference' => $reference,
                'batch_reference' => $batchReference,
                'tds_amount' => 0,
                'processing_fee' => 0,
                'status' => 'failed',
                'processed_by' => $operator->id,
                'processed_at' => now(),
            ]);

            $locked->update(['status' => 'failed']);

            $hold = $locked->holdLedgerEntry()->firstOrFail();
            $this->wallet->releaseHold($hold);

            return $transaction;
        });
    }
}

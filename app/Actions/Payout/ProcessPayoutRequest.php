<?php

namespace App\Actions\Payout;

use App\Models\PayoutRequest;
use App\Models\PayoutTransaction;
use App\Models\User;
use App\Services\RuleVersionService;
use App\Services\WalletLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §11.2 — Super Admin processes a pending payout: creates
 * an immutable `payout_transactions` snapshot (amount, beneficiary, TDS,
 * processing fee), confirms the wallet hold, and marks the request
 * processed. Supports bulk/batch processing via a shared $batchReference —
 * each request is still its own atomic transaction so one failure in a
 * batch never blocks or rolls back the others (DOMAIN_LOGIC.md §21 T-009
 * pre-coding pass; the caller loops over the selected requests).
 */
class ProcessPayoutRequest
{
    public function __construct(
        private readonly RuleVersionService $rules,
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
                    'payout_request' => 'Only a pending payout request can be processed.',
                ]);
            }

            $bankDetail = $locked->bankDetail()->firstOrFail();
            $tdsPercent = (float) $this->rules->value('payout_tds_percent', 0);
            $feePercent = (float) $this->rules->value('payout_processing_fee_percent', 0);
            $amount = (float) $locked->requested_amount;
            $tdsAmount = round($amount * $tdsPercent / 100, 2);
            $feeAmount = round($amount * $feePercent / 100, 2);

            $transaction = PayoutTransaction::create([
                'payout_request_id' => $locked->id,
                'amount_snapshot' => $amount,
                'beneficiary_snapshot' => [
                    'account_holder_name' => $bankDetail->account_holder_name,
                    'account_number' => $bankDetail->account_number,
                    'ifsc_code' => $bankDetail->ifsc_code,
                    'bank_name' => $bankDetail->bank_name,
                ],
                'method' => $method,
                'reference' => $reference,
                'batch_reference' => $batchReference,
                'tds_amount' => $tdsAmount,
                'processing_fee' => $feeAmount,
                'status' => 'processed',
                'processed_by' => $operator->id,
                'processed_at' => now(),
            ]);

            $locked->update(['status' => 'processed']);

            $hold = $locked->holdLedgerEntry()->firstOrFail();
            $this->wallet->confirmHold($hold);

            return $transaction;
        });
    }
}

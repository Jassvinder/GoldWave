<?php

namespace App\Actions\Payments;

use App\Models\PaymentBatch;
use App\Models\User;
use App\Notifications\CashPaymentDecided;
use App\Services\Notifier;
use Illuminate\Support\Facades\DB;

/**
 * DOMAIN_LOGIC.md §3.4 (T-251) / §10.2 point 4 — rejects a combined Cash / GPay-UPI payment: every ID's own payment is
 * rejected with it, no income is generated, and the IDs stay pending without a Customer ID.
 */
class RejectPaymentBatch
{
    public function __invoke(PaymentBatch $batch, User $operator): void
    {
        $rejected = DB::transaction(function () use ($batch, $operator): bool {
            $locked = PaymentBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();

            if ($locked->cash_status !== 'pending_verification') {
                return false;
            }

            $decision = [
                'status' => 'failed',
                'cash_status' => 'rejected',
                'verified_by' => $operator->id,
                'verified_at' => now(),
            ];

            $locked->payments()->where('status', 'pending')->update($decision);
            $locked->update($decision);

            return true;
        });

        if ($rejected) {
            $first = $batch->payments()->with('member.user')->firstOrFail();

            Notifier::toUser($first->member->user, new CashPaymentDecided($first, false));
        }
    }
}

<?php

namespace App\Actions\Payments;

use App\Models\PaymentBatch;
use App\Models\User;
use App\Notifications\CashPaymentDecided;
use App\Services\Notifier;
use Illuminate\Support\Facades\DB;

/**
 * DOMAIN_LOGIC.md §3.4 (T-251) — approves a combined Cash / GPay-UPI payment: every ID's own payment is settled
 * exactly as if approved alone (`ApproveCashPayment::settle`), in ID order, inside ONE transaction. One failure rolls
 * them all back and the combined payment stays pending. Approving twice does nothing.
 */
class ApprovePaymentBatch
{
    public function __construct(private readonly ApproveCashPayment $approve) {}

    public function __invoke(PaymentBatch $batch, User $operator): void
    {
        $approved = DB::transaction(function () use ($batch, $operator): bool {
            $locked = PaymentBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();

            if ($locked->cash_status !== 'pending_verification') {
                return false;
            }

            foreach ($locked->payments as $payment) {
                $this->approve->settle($payment, $operator);
            }

            $locked->update([
                'status' => 'paid',
                'cash_status' => 'approved',
                'verified_by' => $operator->id,
                'verified_at' => now(),
                'paid_at' => now(),
            ]);

            return true;
        });

        if ($approved) {
            $first = $batch->payments()->with('member.user')->firstOrFail();

            // T-141 — one message for the person, naming every ID (`CashPaymentDecided` reads the batch).
            Notifier::toUser($first->member->user, new CashPaymentDecided($first, true));
        }
    }
}

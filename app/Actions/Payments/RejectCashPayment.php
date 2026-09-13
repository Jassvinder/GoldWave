<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §10.2 point 4 — retains the audit trail; generates no
 * compensation; the member/registration stays inactive/unpaid.
 */
class RejectCashPayment
{
    public function __invoke(Payment $payment, User $operator): void
    {
        DB::transaction(function () use ($payment, $operator) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->mode !== 'cash') {
                throw ValidationException::withMessages(['payment' => 'This payment is not a cash payment.']);
            }

            if ($locked->status !== 'pending') {
                return;
            }

            $locked->update([
                'status' => 'failed',
                'cash_status' => 'rejected',
                'verified_by' => $operator->id,
                'verified_at' => now(),
            ]);
        });
    }
}

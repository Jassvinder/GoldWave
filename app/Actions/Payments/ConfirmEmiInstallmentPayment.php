<?php

namespace App\Actions\Payments;

use App\Models\EmiInstallment;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * DOMAIN_LOGIC.md §5/§10 — marks the specific `emi_installments` row a
 * confirmed `type = emi_installment` Payment was raised for as `paid`.
 * Called from ConfirmOnlinePayment/ApproveCashPayment once the Payment
 * itself is already marked paid, inside the same DB transaction. Idempotent:
 * an installment already `paid` is a no-op (mirrors ConfirmOnlinePayment's
 * own payment-level idempotency), so a retried webhook or double
 * cash-approval click can never double-mark an installment.
 */
class ConfirmEmiInstallmentPayment
{
    public function __invoke(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $installment = EmiInstallment::where('payment_id', $payment->id)->lockForUpdate()->first();

            if (! $installment || $installment->status === 'paid') {
                return;
            }

            $installment->update(['status' => 'paid']);
        });
    }
}

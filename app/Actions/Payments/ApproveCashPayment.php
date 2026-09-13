<?php

namespace App\Actions\Payments;

use App\Actions\Registration\ActivateMembershipOnPaymentConfirmed;
use App\Events\PaymentConfirmed;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §10.2 / §3.1 step 8 — Super Admin (or Admin, for a
 * store-recorded cash payment) confirms physical receipt; the operator who
 * activated the member is recorded (§2.1 point 6). Idempotent for the same
 * reason as ConfirmOnlinePayment.
 */
class ApproveCashPayment
{
    public function __construct(
        private readonly ActivateMembershipOnPaymentConfirmed $activate,
        private readonly ConfirmEmiInstallmentPayment $confirmEmiInstallment,
    ) {}

    public function __invoke(Payment $payment, User $operator): void
    {
        $wasAlreadyPaid = DB::transaction(function () use ($payment, $operator) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->mode !== 'cash') {
                throw ValidationException::withMessages(['payment' => 'This payment is not a cash payment.']);
            }

            if ($locked->status === 'paid') {
                return true;
            }

            $locked->update([
                'status' => 'paid',
                'cash_status' => 'approved',
                'verified_by' => $operator->id,
                'verified_at' => now(),
                'paid_at' => now(),
            ]);

            if ($locked->type === 'registration') {
                ($this->activate)($locked->member()->firstOrFail(), $operator->id, $locked);
            } elseif ($locked->type === 'emi_installment') {
                ($this->confirmEmiInstallment)($locked);
            }

            return false;
        });

        if (! $wasAlreadyPaid) {
            event(new PaymentConfirmed($payment->refresh()));
        }
    }
}

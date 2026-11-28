<?php

namespace App\Actions\Payments;

use App\Actions\Registration\ActivateMembershipOnPaymentConfirmed;
use App\Events\PaymentConfirmed;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\CashPaymentDecided;
use App\Services\Notifier;
use App\Services\Payments\PaymentModes;
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
        // T-251 — one ID of a combined payment is approved only with the rest (`ApprovePaymentBatch`).
        if ($payment->payment_batch_id !== null) {
            throw ValidationException::withMessages(['payment' => PaymentModes::BATCH_PART_MESSAGE]);
        }

        $wasAlreadyPaid = DB::transaction(fn (): bool => $this->settle($payment, $operator));

        if (! $wasAlreadyPaid) {
            $payment->refresh();

            // T-141 — tell the member (bell if they can log in yet, plus email + SMS).
            Notifier::toUser($payment->member->user, new CashPaymentDecided($payment, true));
        }
    }

    /**
     * Marks one payment paid and runs everything that follows (activation or the EMI, then the income listeners).
     * Must run inside the caller's transaction; `ApprovePaymentBatch` (T-251) calls it once per ID of a combined
     * payment, so one failure rolls every ID back. No notification here: the caller sends it after the commit.
     *
     * @return bool true when the payment was already paid (nothing done)
     */
    public function settle(Payment $payment, User $operator): bool
    {
        $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

        if (! PaymentModes::needsApproval($locked->mode)) {
            throw ValidationException::withMessages(['payment' => 'This payment does not need a manual approval.']);
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

        // T-228 (15-10-2026) — the income listeners run inside the same transaction, so a failure anywhere rolls
        // the whole approval back and the payment stays pending. Before, they ran after the commit, and a failing
        // listener left a paid payment with part of its income missing.
        event(new PaymentConfirmed($locked->refresh()));

        return false;
    }
}

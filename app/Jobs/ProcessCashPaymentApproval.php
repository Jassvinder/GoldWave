<?php

namespace App\Jobs;

use App\Actions\Payments\ApproveCashPayment;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentSettlementFailed;
use App\Services\Notifier;
use App\Support\PaymentInProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * T-228 (15-10-2026, user decision) — a Cash / GPay-UPI approval runs in the background: activating a deep member and
 * paying every upline can take seconds, and only Super Admin / Admin waits on it. `ApproveCashPayment` does it all in
 * one transaction, so if anything fails nothing is kept: the payment is back in Pending and the company users get a
 * bell alert. One attempt only — the operator decides whether to approve again. The controller marks the payment
 * (`PaymentInProgress`) before queueing; the mark is cleared here.
 */
class ProcessCashPaymentApproval implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public readonly Payment $payment, public readonly User $operator) {}

    public function handle(ApproveCashPayment $approve): void
    {
        try {
            $approve($this->payment, $this->operator);
        } finally {
            PaymentInProgress::release($this->payment->id);
        }
    }

    public function failed(?Throwable $exception): void
    {
        PaymentInProgress::release($this->payment->id);

        Log::error('Payment approval failed and was rolled back', [
            'payment_id' => $this->payment->id,
            'operator_id' => $this->operator->id,
            'error' => $exception?->getMessage(),
        ]);

        Notifier::toSuperAdmins(new PaymentSettlementFailed(
            $this->payment,
            'Payment approval',
            'It is back in Pending. Try again, or check the server log.',
            '/super-admin/cash-payments',
        ));
    }
}

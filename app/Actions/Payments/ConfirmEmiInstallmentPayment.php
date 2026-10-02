<?php

namespace App\Actions\Payments;

use App\Actions\Emi\QuoteFullEmiPayment;
use App\Models\EmiInstallment;
use App\Models\Payment;
use App\Services\WalletLedgerService;
use Illuminate\Support\Facades\DB;

/**
 * DOMAIN_LOGIC.md §5/§10 — marks the specific `emi_installments` row a
 * confirmed `type = emi_installment` Payment was raised for as `paid`.
 * Called from ConfirmOnlinePayment/ApproveCashPayment once the Payment
 * itself is already marked paid, inside the same DB transaction. Idempotent:
 * an installment already `paid` is a no-op (mirrors ConfirmOnlinePayment's
 * own payment-level idempotency), so a retried webhook or double
 * cash-approval click can never double-mark an installment.
 *
 * T-184 (30-09-2026) — a "Pay All Remaining EMIs" payment (`covers_installments` set) is linked to every installment
 * it settles: all of them become paid, and each one's amount becomes what it settled for (on Current Rate, its
 * principal without maintenance), so the paid rows add up to exactly the payment (DOMAIN_LOGIC.md §5 point 9).
 */
class ConfirmEmiInstallmentPayment
{
    public function __construct(
        private readonly QuoteFullEmiPayment $fullPayment,
        private readonly WalletLedgerService $wallet,
    ) {}

    public function __invoke(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $this->settle($payment);
            $this->completeStoreBooking($payment);

            // T-182 (DOMAIN_LOGIC.md §12) — if this cleared the member's last overdue EMI, their held earnings are released.
            $this->wallet->releaseHeldEarnings($payment->member()->firstOrFail());
        });
    }

    /** T-185 (DOMAIN_LOGIC.md §16.13 point 4) — a store Repurchase on EMI whose last EMI is now paid is `completed`. */
    private function completeStoreBooking(Payment $payment): void
    {
        $schedule = EmiInstallment::where('payment_id', $payment->id)->first()?->emiSchedule;

        if ($schedule === null || ! $schedule->isStoreRepurchase()) {
            return;
        }

        $unpaid = $schedule->installments()->whereNotIn('status', ['paid', 'cancelled'])->exists();

        if (! $unpaid) {
            $schedule->storeEmiBooking()->where('status', 'active')->update(['status' => 'completed']);
        }
    }

    private function settle(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $installments = EmiInstallment::where('payment_id', $payment->id)
                ->where('status', '!=', 'paid')
                ->orderBy('installment_no')
                ->lockForUpdate()
                ->get();

            if ($installments->isEmpty()) {
                return;
            }

            if ($payment->covers_installments === null) {
                $installments->first()->update(['status' => 'paid']);

                return;
            }

            $schedule = $installments->first()->emiSchedule()->firstOrFail();
            $amounts = $this->fullPayment->amounts($schedule, $installments);
            // Should already match; any difference (a schedule changed while pending) goes into the last row.
            $amounts[count($amounts) - 1] = round($amounts[count($amounts) - 1] + (float) $payment->amount - array_sum($amounts), 2);

            foreach ($installments->values() as $i => $installment) {
                $installment->update(['status' => 'paid', 'amount' => $amounts[$i]]);
            }
        });
    }
}

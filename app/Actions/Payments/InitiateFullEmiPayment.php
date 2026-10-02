<?php

namespace App\Actions\Payments;

use App\Actions\Emi\QuoteFullEmiPayment;
use App\Models\CurrentRateBookingRequest;
use App\Models\EmiInstallment;
use App\Models\EmiSchedule;
use App\Models\Member;
use App\Models\Payment;
use App\Notifications\CashPaymentAwaitingApproval;
use App\Services\Notifier;
use App\Services\Payments\PaymentModes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * DOMAIN_LOGIC.md §5 point 9 (T-184, 30-09-2026) — "Pay All Remaining EMIs": one `emi_installment` payment for every
 * unpaid installment (`covers_installments` = how many), each of which is linked to it; confirming the payment marks
 * them all paid (`ConfirmEmiInstallmentPayment`). A pending Current Rate booking request is cancelled here, so the
 * schedule cannot change under the pending payment. Same Online / Cash flow as a single EMI.
 */
class InitiateFullEmiPayment
{
    public const BOOKING_CANCEL_MESSAGE = 'Cancelled because all remaining EMIs were paid in full.';

    public function __construct(private readonly QuoteFullEmiPayment $quote) {}

    /** T-185 — `$schedule` picks a store Repurchase on EMI; null is the plan's own schedule. */
    public function __invoke(Member $member, string $mode, ?EmiSchedule $schedule = null): Payment
    {
        $payment = DB::transaction(function () use ($member, $mode, $schedule) {
            $schedule = $schedule === null
                ? $member->emiSchedule()->lockForUpdate()->firstOrFail()
                : EmiSchedule::whereKey($schedule->id)->where('member_id', $member->id)->lockForUpdate()->firstOrFail();
            $schedule->installments()->lockForUpdate()->get();

            $quote = ($this->quote)($member, $schedule);

            $payment = Payment::create([
                'member_id' => $member->id,
                'type' => 'emi_installment',
                'amount' => $quote['amount'],
                'covers_installments' => $quote['installments']->count(),
                'mode' => $mode,
                'status' => 'pending',
                'idempotency_key' => (string) Str::uuid(),
                'cash_status' => PaymentModes::initialApprovalStatus($mode),
            ]);

            EmiInstallment::whereIn('id', $quote['installments']->pluck('id'))->update(['payment_id' => $payment->id]);

            CurrentRateBookingRequest::where('emi_schedule_id', $schedule->id)->where('status', 'pending')->update([
                'status' => 'cancelled',
                'decided_at' => now(),
                'cancel_message' => self::BOOKING_CANCEL_MESSAGE,
            ]);

            return $payment;
        });

        // T-141 — a cash payment waits for the Super Admin's approval, so the Super Admin is told.
        if (PaymentModes::needsApproval($payment->mode)) {
            Notifier::toSuperAdmins(new CashPaymentAwaitingApproval($payment));
        }

        return $payment;
    }
}

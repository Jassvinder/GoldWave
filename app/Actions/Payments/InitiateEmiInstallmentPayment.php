<?php

namespace App\Actions\Payments;

use App\Models\EmiInstallment;
use App\Models\EmiSchedule;
use App\Models\Member;
use App\Models\Payment;
use App\Notifications\CashPaymentAwaitingApproval;
use App\Services\Notifier;
use App\Services\Payments\PaymentModes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §5 item 8 / §10 — creates the `payments` row (type =
 * `emi_installment`) that starts either the Online or Cash "Payment In" flow
 * for a specific installment. Ordering rule (§5 item 8): only the schedule's
 * single earliest-unpaid installment can be paid. T-241 (15-11-2026) — that
 * may be an `upcoming` one (advance payment), so a member can pay ahead one
 * EMI at a time, up to the whole schedule. Reuses an already-pending payment
 * for the same installment instead of creating a duplicate row if the member
 * re-attempts (e.g. after a failed/abandoned gateway session).
 */
class InitiateEmiInstallmentPayment
{
    /** Statuses of an installment that is not paid yet and can be paid (in installment order). */
    public const PAYABLE_STATUSES = ['overdue', 'due', 'upcoming'];

    /** The schedule's earliest unpaid installment — the only one that can be paid now (§5 item 8). */
    public static function nextPayable(int $scheduleId, bool $lock = false): ?EmiInstallment
    {
        $query = EmiInstallment::where('emi_schedule_id', $scheduleId)
            ->whereIn('status', self::PAYABLE_STATUSES)
            ->orderBy('installment_no');

        return ($lock ? $query->lockForUpdate() : $query)->first();
    }

    /** `$notifyApprovers` is false when company staff record the cash themselves and settle it at once (T-242). */
    public function __invoke(Member $member, EmiInstallment $installment, string $mode, bool $notifyApprovers = true): Payment
    {
        $newCashPayment = null;

        $payment = DB::transaction(function () use ($member, $installment, $mode, &$newCashPayment) {
            // T-185 — the plan's schedule or a store Repurchase on EMI; either way it must be this member's own.
            $schedule = EmiSchedule::whereKey($installment->emi_schedule_id)->where('member_id', $member->id)->first();

            if ($schedule === null) {
                throw ValidationException::withMessages([
                    'installment' => 'This installment does not belong to your EMI schedule.',
                ]);
            }

            if ($schedule->isStoreRepurchase() && $schedule->storeEmiBooking?->status !== 'active') {
                throw ValidationException::withMessages([
                    'installment' => 'This Repurchase on EMI is no longer running.',
                ]);
            }

            $nextPayable = self::nextPayable($schedule->id, lock: true);

            if (! $nextPayable || $nextPayable->id !== $installment->id) {
                throw ValidationException::withMessages([
                    'installment' => 'EMIs are paid in order — pay your earliest unpaid EMI first.',
                ]);
            }

            if ($installment->payment_id) {
                $existing = Payment::find($installment->payment_id);
                // T-184 — a pending "Pay All Remaining EMIs" payment covers this installment too.
                if ($existing && $existing->status === 'pending' && $existing->covers_installments !== null) {
                    throw ValidationException::withMessages([
                        'installment' => 'Your payment for all remaining EMIs is waiting for confirmation.',
                    ]);
                }

                // T-251 — this EMI is in a combined "Pay for all my IDs" payment, decided only as one.
                if ($existing && $existing->status === 'pending' && $existing->payment_batch_id !== null) {
                    throw ValidationException::withMessages([
                        'installment' => 'This EMI is in a combined payment for all your IDs, waiting for confirmation.',
                    ]);
                }

                if ($existing && $existing->status === 'pending') {
                    return $existing;
                }
            }

            $payment = Payment::create([
                'member_id' => $member->id,
                'type' => 'emi_installment',
                'amount' => $installment->amount,
                'mode' => $mode,
                'status' => 'pending',
                'idempotency_key' => (string) Str::uuid(),
                'cash_status' => PaymentModes::initialApprovalStatus($mode),
            ]);

            $installment->update(['payment_id' => $payment->id]);

            if (PaymentModes::needsApproval($mode)) {
                $newCashPayment = $payment;
            }

            return $payment;
        });

        // T-141 — a new cash payment waits for the Super Admin's approval, so the Super Admin is told (bell + page).
        // Re-using an already-pending payment above never notifies a second time.
        if ($newCashPayment !== null && $notifyApprovers) {
            Notifier::toSuperAdmins(new CashPaymentAwaitingApproval($newCashPayment));
        }

        return $payment;
    }
}

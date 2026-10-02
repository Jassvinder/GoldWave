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
 * for a specific installment. Enforces the "next-due only" ordering rule
 * (§5 item 8, RESOLVED 13-09-2026): a member may only initiate payment for
 * their schedule's single earliest-unpaid installment, never skip ahead to a
 * later `upcoming` one. Reuses an already-pending payment for the same
 * installment instead of creating a duplicate row if the member re-attempts
 * (e.g. after a failed/abandoned gateway session).
 */
class InitiateEmiInstallmentPayment
{
    public function __invoke(Member $member, EmiInstallment $installment, string $mode): Payment
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

            $nextPayable = EmiInstallment::where('emi_schedule_id', $schedule->id)
                ->whereIn('status', ['due', 'overdue'])
                ->orderBy('installment_no')
                ->lockForUpdate()
                ->first();

            if (! $nextPayable || $nextPayable->id !== $installment->id) {
                throw ValidationException::withMessages([
                    'installment' => 'Only your next-due installment can be paid — no advance/skip-ahead payment.',
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
        if ($newCashPayment !== null) {
            Notifier::toSuperAdmins(new CashPaymentAwaitingApproval($newCashPayment));
        }

        return $payment;
    }
}

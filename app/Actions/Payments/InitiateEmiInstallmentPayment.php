<?php

namespace App\Actions\Payments;

use App\Models\EmiInstallment;
use App\Models\Member;
use App\Models\Payment;
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
        return DB::transaction(function () use ($member, $installment, $mode) {
            $schedule = $member->emiSchedule()->firstOrFail();

            if ($installment->emi_schedule_id !== $schedule->id) {
                throw ValidationException::withMessages([
                    'installment' => 'This installment does not belong to your EMI schedule.',
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
                'cash_status' => $mode === 'cash' ? 'pending_verification' : null,
            ]);

            $installment->update(['payment_id' => $payment->id]);

            return $payment;
        });
    }
}

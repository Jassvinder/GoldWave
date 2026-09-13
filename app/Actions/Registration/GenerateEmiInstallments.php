<?php

namespace App\Actions\Registration;

use App\Models\EmiInstallment;
use App\Models\Member;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * DOMAIN_LOGIC.md §5 item 7 — generates every `emi_installments` row for a
 * member's EMI membership on activation-date-anniversary due dates
 * (installment 1 due on the activation date itself; each later installment
 * due the same day-of-month one calendar month on — `addMonthsNoOverflow` so
 * a 31st-of-the-month activation degrades to the shorter month's last day
 * instead of overflowing into the following month). Idempotent: a schedule
 * that already has installments is a no-op, so a retried activation call
 * never duplicates the schedule.
 *
 * **Installment #1 = the registration payment (RESOLVED 13-09-2026, user
 * confirmation):** the amount that funded registration/activation for an EMI
 * plan is exactly one installment's amount (`RegisterMember`), so it counts
 * as installment #1 rather than a separate charge — that row is created
 * already `status = paid`, linked to `$registrationPayment`. The member is
 * never asked to pay installment #1 a second time.
 *
 * Called by ActivateMembershipOnPaymentConfirmed right after activation, for
 * EMI plans only — this is the "full emi_installments schedule generation"
 * T-003's ActivateMembershipOnPaymentConfirmed explicitly stopped short of
 * (see that class's docblock).
 */
class GenerateEmiInstallments
{
    public function __invoke(Member $member, Payment $registrationPayment): void
    {
        DB::transaction(function () use ($member, $registrationPayment) {
            $schedule = $member->emiSchedule()->lockForUpdate()->first();

            if (! $schedule) {
                return;
            }

            if ($schedule->installments()->exists()) {
                return;
            }

            $activationDate = Carbon::parse($member->activated_at ?? now());
            $today = now()->startOfDay();

            $rows = [];

            for ($installmentNo = 1; $installmentNo <= $schedule->total_installments; $installmentNo++) {
                $dueDate = $activationDate->copy()->addMonthsNoOverflow($installmentNo - 1)->startOfDay();
                $isFirstInstallment = $installmentNo === 1;

                $rows[] = [
                    'emi_schedule_id' => $schedule->id,
                    'installment_no' => $installmentNo,
                    'due_date' => $dueDate->toDateString(),
                    'amount' => $schedule->installment_amount,
                    'status' => $isFirstInstallment
                        // DOMAIN_LOGIC.md §5 item 7: no grace period — a due date reached today or
                        // earlier at generation time is already 'due', never left as 'upcoming'.
                        ? 'paid'
                        : ($dueDate->lte($today) ? 'due' : 'upcoming'),
                    'payment_id' => $isFirstInstallment ? $registrationPayment->id : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            EmiInstallment::insert($rows);
        });
    }
}

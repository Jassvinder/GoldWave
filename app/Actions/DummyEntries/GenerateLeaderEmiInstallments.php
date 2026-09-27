<?php

namespace App\Actions\DummyEntries;

use App\Models\EmiInstallment;
use App\Models\Member;
use Illuminate\Support\Carbon;

/**
 * T-149 (22-09-2026, user decision) — once a real leader's identity is entered into a dummy entry
 * (`AssignDummyEntryToLeader`), this generates the rest of their EMI schedule (installment #2 onward; #1 was already
 * seeded and paid when the entry was generated, `GenerateDailyDummyEntries`). No matter how many months the entry sat
 * unassigned, the schedule "starts fresh" from #2 anchored to **today** (the assignment date) — never catching up or
 * back-dating for the elapsed time — matching `GenerateEmiInstallments`' own activation-date-anniversary/no-grace-period
 * pattern, just anchored at assignment instead of registration. From here on this is an entirely ordinary EMI schedule:
 * the leader pays installment #2 onward themselves through the normal EMI page, and each confirmed payment triggers
 * compensation exactly like any other member's (no special-casing needed past this point).
 */
class GenerateLeaderEmiInstallments
{
    public function __invoke(Member $leader): void
    {
        $schedule = $leader->emiSchedule()->first();

        if ($schedule === null || $schedule->installments()->count() > 1) {
            return; // No EMI plan, or already generated (idempotency guard, mirrors GenerateEmiInstallments).
        }

        $anchor = Carbon::now()->startOfDay();
        $today = $anchor->copy();
        $rows = [];

        for ($installmentNo = 2; $installmentNo <= $schedule->total_installments; $installmentNo++) {
            $dueDate = $anchor->copy()->addMonthsNoOverflow($installmentNo - 2);

            $rows[] = [
                'emi_schedule_id' => $schedule->id,
                'installment_no' => $installmentNo,
                'due_date' => $dueDate->toDateString(),
                'amount' => $schedule->installment_amount,
                // Same no-grace-period rule as GenerateEmiInstallments: a due date reached today is already 'due'.
                'status' => $dueDate->lte($today) ? 'due' : 'upcoming',
                'payment_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            EmiInstallment::insert($rows);
        }
    }
}

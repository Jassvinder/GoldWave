<?php

namespace App\Actions\Emi;

use App\Models\EmiRateBookingEvent;
use App\Models\EmiSchedule;
use App\Models\Member;
use App\Models\User;
use App\Support\Dates;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §3.0 "Super Admin Revert" — undoes a member's Current Rate booking: every unpaid installment goes
 * back to the plan's plain amount and the schedule returns to Future Rate. Only a Super Admin can do this (the member
 * cannot undo a booking themselves; they contact the Super Admin). Refused once any EMI has been paid after the booking,
 * because the member would then hold payments made under two different amounts — that case is never guessed.
 *
 * Paid installments and every due date are untouched. Written to `emi_rate_booking_events` with the reason.
 */
class RevertCurrentRateBooking
{
    /** Why this schedule cannot be reverted right now, or null when it can. Shared by the page (to show/hide the button) and the Action. */
    public function blocker(EmiSchedule $schedule): ?string
    {
        if ($schedule->rate_booking_method !== 'current_rate') {
            return 'This schedule is not booked at the Current Rate.';
        }

        if ($schedule->current_rate_booked_at === null) {
            return 'This schedule was Current Rate from registration, not booked afterwards, so it cannot be reverted.';
        }

        $installments = $schedule->installments()->get();

        if ($installments->where('status', 'paid')->count() !== (int) $schedule->installments_paid_at_booking) {
            return 'An EMI has been paid since the booking, so it can no longer be reverted.';
        }

        $awaitingConfirmation = $schedule->installments()
            ->where('status', '!=', 'paid')
            ->whereHas('payment', fn ($query) => $query->where('status', 'pending'))
            ->exists();

        if ($awaitingConfirmation) {
            return 'An EMI payment is awaiting confirmation. Revert once it is confirmed or rejected.';
        }

        return null;
    }

    public function __invoke(Member $member, User $operator, string $reason): EmiSchedule
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Enter a reason for the revert.']);
        }

        return DB::transaction(function () use ($member, $operator, $reason) {
            $schedule = $member->emiSchedule()->with('membershipPlan')->lockForUpdate()->first();

            if ($schedule === null || $schedule->membershipPlan === null) {
                throw ValidationException::withMessages(['revert' => 'This member has no EMI plan.']);
            }

            $schedule->installments()->lockForUpdate()->get();

            $blocker = $this->blocker($schedule);

            if ($blocker !== null) {
                throw ValidationException::withMessages(['revert' => $blocker]);
            }

            $planAmount = (float) $schedule->membershipPlan->amount;

            $before = [
                'installment_amount' => (float) $schedule->installment_amount,
                'rate_per_gram' => (float) $schedule->rate_per_gram_at_booking,
                'fixed_weight_grams' => (float) $schedule->fixed_weight_grams,
                'maintenance_cost' => (float) $schedule->maintenance_cost,
                'booked_at' => Dates::date($schedule->current_rate_booked_at),
                'installments_paid_at_booking' => $schedule->installments_paid_at_booking,
                'amount_paid_at_booking' => (float) $schedule->amount_paid_at_booking,
            ];

            $schedule->installments()->where('status', '!=', 'paid')->update(['amount' => $planAmount]);

            $schedule->update([
                'rate_booking_method' => 'future_rate',
                'installment_amount' => $planAmount,
                'metal_rate_id' => null,
                'rate_per_gram_at_booking' => null,
                'fixed_weight_grams' => null,
                'maintenance_cost' => null,
                'rule_version_id' => null,
                'current_rate_booked_at' => null,
                'installments_paid_at_booking' => null,
                'amount_paid_at_booking' => null,
            ]);

            EmiRateBookingEvent::create([
                'emi_schedule_id' => $schedule->id,
                'event' => 'reverted',
                'performed_by_user_id' => $operator->id,
                'reason' => $reason,
                'details' => ['before' => $before, 'after' => ['installment_amount' => $planAmount]],
            ]);

            return $schedule->fresh() ?? $schedule;
        });
    }
}

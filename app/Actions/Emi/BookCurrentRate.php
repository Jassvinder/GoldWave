<?php

namespace App\Actions\Emi;

use App\Models\EmiRateBookingEvent;
use App\Models\EmiSchedule;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §3.0 "Switching to Current Rate Booking" (T-116) — moves a member's EMI schedule from Future Rate to
 * Current Rate: every not-yet-paid installment gets the new EMI amount and the schedule records the locked rate,
 * weight, maintenance and how much had already been paid (so the calculation stays reproducible, §22). One-way and
 * one-time — `QuoteCurrentRateBooking` refuses a schedule that is already on Current Rate.
 *
 * The caller passes the rate id and paid-EMI count the member saw in the popup; the quote is re-derived here inside
 * the transaction (with the schedule and its installments locked) and the booking is rejected if either changed in
 * between, so a member never confirms one set of numbers and is charged another. Paid installments and every due
 * date are left untouched.
 */
class BookCurrentRate
{
    public function __construct(private readonly QuoteCurrentRateBooking $quote) {}

    /**
     * T-166 (28-09-2026) — since bookings need Super Admin approval, this is run by `ApproveCurrentRateBookingRequest`
     * with the approving Super Admin as `$performedBy`, at the approval day's rate and pending EMIs.
     */
    public function __invoke(Member $member, int $quotedMetalRateId, int $quotedPaidInstallments, ?User $performedBy = null): EmiSchedule
    {
        return DB::transaction(function () use ($member, $quotedMetalRateId, $quotedPaidInstallments, $performedBy) {
            $schedule = $member->emiSchedule()->lockForUpdate()->first();

            if ($schedule === null) {
                throw ValidationException::withMessages(['booking' => 'You do not have an EMI plan.']);
            }

            $schedule->installments()->lockForUpdate()->get();

            $quote = ($this->quote)($member);

            if ($quote['metal_rate_id'] !== $quotedMetalRateId || $quote['paid_installments'] !== $quotedPaidInstallments) {
                throw ValidationException::withMessages([
                    'booking' => 'The rate or your paid EMIs changed since you opened this. Please review the new figures and confirm again.',
                ]);
            }

            // T-167 — each unpaid installment gets its own amount, in installment order (maintenance declines monthly).
            $unpaid = $schedule->installments()->where('status', '!=', 'paid')->orderBy('installment_no')->get();

            foreach ($unpaid->values() as $i => $installment) {
                $installment->update(['amount' => $quote['installment_amounts'][$i] ?? $quote['last_installment_amount']]);
            }

            $schedule->update([
                'rate_booking_method' => 'current_rate',
                'installment_amount' => $quote['installment_amount'],
                'metal_rate_id' => $quote['metal_rate_id'],
                'rate_per_gram_at_booking' => $quote['rate_per_gram'],
                'fixed_weight_grams' => $quote['fixed_weight_grams'],
                'maintenance_cost' => $quote['maintenance_cost'],
                'rule_version_id' => $quote['rule_version_id'],
                'current_rate_booked_at' => now(),
                'installments_paid_at_booking' => $quote['paid_installments'],
                'amount_paid_at_booking' => $quote['paid_amount'],
            ]);

            EmiRateBookingEvent::create([
                'emi_schedule_id' => $schedule->id,
                'event' => 'booked',
                'performed_by_user_id' => $performedBy->id ?? $member->user_id,
                'details' => [
                    'rate_per_gram' => $quote['rate_per_gram'],
                    'fixed_weight_grams' => $quote['fixed_weight_grams'],
                    'metal_value' => $quote['metal_value'],
                    'making_charge_percent' => $quote['making_charge_percent'],
                    'making_charges' => $quote['making_charges'],
                    'total_value' => $quote['total_value'],
                    'paid_installments' => $quote['paid_installments'],
                    'paid_amount' => $quote['paid_amount'],
                    'remaining_value' => $quote['remaining_value'],
                    'pending_installments' => $quote['pending_installments'],
                    'maintenance_cost' => $quote['maintenance_cost'],
                    'installment_amount' => $quote['installment_amount'],
                ],
            ]);

            return $schedule->fresh() ?? $schedule;
        });
    }
}

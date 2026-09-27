<?php

namespace App\Actions\Emi;

use App\Actions\Registration\CalculateEmiRateBooking;
use App\Models\EmiSchedule;
use App\Models\Member;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §3.0 "Switching to Current Rate Booking" (T-116) — what a member on Future Rate would get by booking
 * at the Current Rate right now: the numbers behind the Membership Plan page's "Book at Current Rate" popup, and the
 * exact figures `BookCurrentRate` re-derives and applies. Read-only. Throws a ValidationException (`booking`) when the
 * member cannot book at all, so the page can simply hide the button and `BookCurrentRate` can refuse.
 *
 * The maths is not here — `CalculateEmiRateBooking` owns the formula (paid EMIs credited, 1% maintenance on the
 * remaining value); this Action only gathers the member's paid EMIs and enforces the eligibility guards.
 */
class QuoteCurrentRateBooking
{
    public function __construct(private readonly CalculateEmiRateBooking $calculate) {}

    /**
     * @return array{
     *     schedule: EmiSchedule,
     *     metal: string|null,
     *     fixed_weight_grams: float,
     *     metal_rate_id: int,
     *     rate_per_gram: float,
     *     rule_version_id: int|null,
     *     total_value: float,
     *     paid_installments: int,
     *     paid_amount: float,
     *     remaining_value: float,
     *     pending_installments: int,
     *     maintenance_cost: float,
     *     installment_amount: float,
     *     total_remaining_payable: float,
     * }
     */
    public function __invoke(Member $member): array
    {
        $schedule = $member->emiSchedule()->with('membershipPlan')->first();

        if ($schedule === null || $schedule->membershipPlan === null) {
            throw ValidationException::withMessages(['booking' => 'You do not have an EMI plan.']);
        }

        if ($member->status !== 'active') {
            throw ValidationException::withMessages(['booking' => 'Your membership is not active yet.']);
        }

        if ($schedule->rate_booking_method !== 'future_rate') {
            throw ValidationException::withMessages(['booking' => 'This plan is already booked at the Current Rate.']);
        }

        $installments = $schedule->installments()->get();

        if ($installments->isEmpty() || $installments->where('status', '!=', 'paid')->isEmpty()) {
            throw ValidationException::withMessages(['booking' => 'There is no unpaid EMI left to book.']);
        }

        $awaitingConfirmation = $schedule->installments()
            ->where('status', '!=', 'paid')
            ->whereHas('payment', fn ($query) => $query->where('status', 'pending'))
            ->exists();

        if ($awaitingConfirmation) {
            throw ValidationException::withMessages(['booking' => 'An EMI payment is still awaiting confirmation. Try again once it is confirmed.']);
        }

        $paid = $installments->where('status', 'paid');
        $paidInstallments = $paid->count();
        $paidAmount = round((float) $paid->sum('amount'), 2);

        $booking = ($this->calculate)($schedule->membershipPlan, 'current_rate', $paidInstallments, $paidAmount);

        $installmentAmount = $booking['installment_amount'];
        $pending = (int) $booking['pending_installments'];

        return [
            'schedule' => $schedule,
            'metal' => $schedule->membershipPlan->product_category,
            'fixed_weight_grams' => (float) $booking['fixed_weight_grams'],
            'metal_rate_id' => (int) $booking['metal_rate_id'],
            'rate_per_gram' => (float) $booking['rate_per_gram'],
            'rule_version_id' => $booking['rule_version_id'],
            'total_value' => (float) $booking['total_value'],
            'paid_installments' => $paidInstallments,
            'paid_amount' => $paidAmount,
            'remaining_value' => (float) $booking['remaining_value'],
            'pending_installments' => $pending,
            'maintenance_cost' => (float) $booking['maintenance_cost'],
            'installment_amount' => $installmentAmount,
            'total_remaining_payable' => round($installmentAmount * $pending, 2),
        ];
    }
}

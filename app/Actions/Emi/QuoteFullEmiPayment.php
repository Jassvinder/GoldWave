<?php

namespace App\Actions\Emi;

use App\Models\EmiInstallment;
use App\Models\EmiRateBookingEvent;
use App\Models\EmiSchedule;
use App\Models\Member;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §5 point 9 (T-184, 30-09-2026) — what "Pay All Remaining EMIs" costs. Future Rate: the unpaid
 * installments as they stand. Current Rate: only their principal, without maintenance — `remaining value at booking −
 * principal × EMIs paid since booking`, split into the same principal per installment the T-167 schedule uses (the last
 * one takes the rounding remainder), so the rewritten rows add up to exactly the amount paid (Docs/TEST.md scenario 37).
 */
class QuoteFullEmiPayment
{
    /**
     * The member-facing quote, with every guard.
     *
     * @return array{schedule: EmiSchedule, installments: Collection<int, EmiInstallment>, amounts: list<float>, amount: float, regular_total: float, saving: float}
     */
    public function __invoke(Member $member, ?EmiSchedule $schedule = null): array
    {
        // T-185 — the plan's own schedule by default, or a store Repurchase on EMI of this member's.
        $schedule = $schedule === null
            ? $member->emiSchedule()->first()
            : EmiSchedule::whereKey($schedule->id)->where('member_id', $member->id)->first();

        if ($schedule === null) {
            throw ValidationException::withMessages(['full_payment' => 'You do not have an EMI plan.']);
        }

        if ($schedule->isStoreRepurchase() && $schedule->storeEmiBooking?->status !== 'active') {
            throw ValidationException::withMessages(['full_payment' => 'This Repurchase on EMI is no longer running.']);
        }

        $unpaid = $this->unpaidInstallments($schedule);

        if ($unpaid->isEmpty()) {
            throw ValidationException::withMessages(['full_payment' => 'Every EMI is already paid.']);
        }

        if ($unpaid->contains(fn (EmiInstallment $installment) => $installment->payment?->status === 'pending')) {
            throw ValidationException::withMessages([
                'full_payment' => 'A payment is still waiting for confirmation. Pay all remaining EMIs once it is confirmed or rejected.',
            ]);
        }

        $amounts = $this->amounts($schedule, $unpaid);
        $amount = round(array_sum($amounts), 2);
        $regularTotal = round((float) $unpaid->sum('amount'), 2);

        return [
            'schedule' => $schedule,
            'installments' => $unpaid,
            'amounts' => $amounts,
            'amount' => $amount,
            'regular_total' => $regularTotal,
            'saving' => round($regularTotal - $amount, 2),
        ];
    }

    /** @return Collection<int, EmiInstallment> */
    public function unpaidInstallments(EmiSchedule $schedule): Collection
    {
        return $schedule->installments()->with('payment')->whereNotIn('status', ['paid', 'cancelled'])->orderBy('installment_no')->get();
    }

    /**
     * The amount each unpaid installment settles for, in installment order. No guards — also used when the payment
     * is confirmed, by which time the full payment itself is the pending one.
     *
     * @param  Collection<int, EmiInstallment>  $unpaid
     * @return list<float>
     */
    public function amounts(EmiSchedule $schedule, Collection $unpaid): array
    {
        if ($schedule->rate_booking_method !== 'current_rate') {
            $amounts = [];

            foreach ($unpaid as $installment) {
                $amounts[] = round((float) $installment->amount, 2);
            }

            return $amounts;
        }

        $booked = EmiRateBookingEvent::where('emi_schedule_id', $schedule->id)->where('event', 'booked')->latest('id')->first();
        $remainingAtBooking = (float) ($booked?->details['remaining_value'] ?? 0);
        $pendingAtBooking = (int) ($booked?->details['pending_installments'] ?? 0);

        if ($remainingAtBooking <= 0 || $pendingAtBooking < $unpaid->count()) {
            throw ValidationException::withMessages([
                'full_payment' => 'This Current Rate booking has no booking record to calculate from. Please contact GoldWave.',
            ]);
        }

        $principal = round($remainingAtBooking / $pendingAtBooking, 2);
        $paidSinceBooking = $pendingAtBooking - $unpaid->count();
        $remainingPrincipal = round($remainingAtBooking - $principal * $paidSinceBooking, 2);

        // Every unpaid installment settles for one principal; the last takes whatever rounding remainder is left.
        $count = $unpaid->count();
        $amounts = [];

        for ($i = 1; $i < $count; $i++) {
            $amounts[] = $principal;
        }

        $amounts[] = round($remainingPrincipal - $principal * ($count - 1), 2);

        return $amounts;
    }
}

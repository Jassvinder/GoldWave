<?php

namespace App\Actions\Emi;

use App\Models\CurrentRateBookingRequest;
use App\Models\Member;
use App\Notifications\CurrentRateBookingRequested;
use App\Services\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * T-166 (28-09-2026, user decision — DOMAIN_LOGIC.md §3.0 note): the member's "Book at Current Rate" now only files a
 * request; nothing about their EMIs changes until Super Admin approves it (at the approval day's rate). The same
 * eligibility guards as a booking apply up front (`QuoteCurrentRateBooking`), one pending request at a time, and every
 * Super Admin is notified immediately on all channels.
 */
class RequestCurrentRateBooking
{
    public function __construct(private readonly QuoteCurrentRateBooking $quote) {}

    public function __invoke(Member $member): CurrentRateBookingRequest
    {
        $bookingRequest = DB::transaction(function () use ($member) {
            $schedule = $member->emiSchedule()->lockForUpdate()->first();

            if ($schedule === null) {
                throw ValidationException::withMessages(['booking' => 'You do not have an EMI plan.']);
            }

            if (CurrentRateBookingRequest::where('emi_schedule_id', $schedule->id)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['booking' => 'Your request is already waiting for Super Admin approval.']);
            }

            $quote = ($this->quote)($member);

            return CurrentRateBookingRequest::create([
                'emi_schedule_id' => $schedule->id,
                'member_id' => $member->id,
                'status' => 'pending',
                // What the member saw when asking — the real figures are worked out again on the approval day.
                'estimate' => [
                    'metal' => $quote['metal'],
                    'fixed_weight_grams' => $quote['fixed_weight_grams'],
                    'rate_per_gram' => $quote['rate_per_gram'],
                    'total_value' => $quote['total_value'],
                    'paid_installments' => $quote['paid_installments'],
                    'installment_amount' => $quote['installment_amount'],
                ],
            ]);
        });

        Notifier::toSuperAdmins(new CurrentRateBookingRequested($bookingRequest->load('member.user')));

        return $bookingRequest;
    }
}

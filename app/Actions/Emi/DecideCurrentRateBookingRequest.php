<?php

namespace App\Actions\Emi;

use App\Models\CurrentRateBookingRequest;
use App\Models\User;
use App\Notifications\CurrentRateBookingDecided;
use App\Services\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * T-166 (28-09-2026, user decision — DOMAIN_LOGIC.md §3.0 note): Super Admin approves or cancels a member's Current Rate
 * booking request.
 *
 * - **Approve** applies the booking through `BookCurrentRate` — at the approval day's rate, over the EMIs still pending
 *   that day (an EMI paid meanwhile is no longer pending; an overdue one still is). The Super Admin posts the rate id
 *   and paid-EMI count they were shown, and a stale view is refused, exactly like the member's old direct booking.
 * - **Cancel** needs a message, which the member then sees on their EMI Schedule page; they may request again.
 *
 * The member is notified either way.
 */
class DecideCurrentRateBookingRequest
{
    public function __construct(private readonly BookCurrentRate $book) {}

    public function approve(CurrentRateBookingRequest $bookingRequest, User $superAdmin, int $quotedMetalRateId, int $quotedPaidInstallments): CurrentRateBookingRequest
    {
        $decided = DB::transaction(function () use ($bookingRequest, $superAdmin, $quotedMetalRateId, $quotedPaidInstallments) {
            $locked = $this->lockPending($bookingRequest);

            ($this->book)($locked->member()->firstOrFail(), $quotedMetalRateId, $quotedPaidInstallments, $superAdmin);

            $locked->update(['status' => 'approved', 'decided_by' => $superAdmin->id, 'decided_at' => now()]);

            return $locked;
        });

        Notifier::toUser($decided->member->user, new CurrentRateBookingDecided($decided));

        return $decided;
    }

    public function cancel(CurrentRateBookingRequest $bookingRequest, User $superAdmin, string $message): CurrentRateBookingRequest
    {
        if (trim($message) === '') {
            throw ValidationException::withMessages(['cancel_message' => 'Tell the member why the request is cancelled.']);
        }

        $decided = DB::transaction(function () use ($bookingRequest, $superAdmin, $message) {
            $locked = $this->lockPending($bookingRequest);

            $locked->update([
                'status' => 'cancelled',
                'decided_by' => $superAdmin->id,
                'decided_at' => now(),
                'cancel_message' => trim($message),
            ]);

            return $locked;
        });

        Notifier::toUser($decided->member->user, new CurrentRateBookingDecided($decided));

        return $decided;
    }

    private function lockPending(CurrentRateBookingRequest $bookingRequest): CurrentRateBookingRequest
    {
        $locked = CurrentRateBookingRequest::whereKey($bookingRequest->id)->lockForUpdate()->firstOrFail();

        if ($locked->status !== 'pending') {
            throw ValidationException::withMessages(['booking' => 'This request has already been decided.']);
        }

        return $locked;
    }
}

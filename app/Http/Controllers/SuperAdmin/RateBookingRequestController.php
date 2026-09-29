<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Emi\DecideCurrentRateBookingRequest;
use App\Actions\Emi\QuoteCurrentRateBooking;
use App\Http\Controllers\Controller;
use App\Models\CurrentRateBookingRequest;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-166 (28-09-2026, user decision — DOMAIN_LOGIC.md §3.0 note): Super Admin's queue of Current Rate booking
 * requests. Each pending row shows what approving **now** would lock (today's rate, the EMIs pending today) plus the
 * metal to buy (plan, metal, weight, rate, total value); Approve applies it, Cancel sends a message to the member.
 */
class RateBookingRequestController extends Controller
{
    public function index(QuoteCurrentRateBooking $quote): Response
    {
        $pending = CurrentRateBookingRequest::with(['member.user', 'member.membershipPlan'])
            ->where('status', 'pending')
            ->orderBy('id')
            ->get()
            ->map(function (CurrentRateBookingRequest $bookingRequest) use ($quote): array {
                try {
                    $q = $quote($bookingRequest->member);
                    $now = [
                        'metal' => $q['metal'],
                        'fixed_weight_grams' => $q['fixed_weight_grams'],
                        'metal_rate_id' => $q['metal_rate_id'],
                        'rate_per_gram' => $q['rate_per_gram'],
                        'metal_value' => $q['metal_value'],
                        'making_charges' => $q['making_charges'],
                        'total_value' => $q['total_value'],
                        'paid_installments' => $q['paid_installments'],
                        'pending_installments' => $q['pending_installments'],
                        'installment_amount' => $q['installment_amount'],
                        'last_installment_amount' => $q['last_installment_amount'],
                    ];
                    $blocked = null;
                } catch (ValidationException $e) {
                    // e.g. an EMI payment is still awaiting confirmation — approval must wait.
                    $now = null;
                    $blocked = collect($e->errors())->flatten()->first();
                }

                return [
                    'id' => $bookingRequest->id,
                    'requested_at' => Dates::date($bookingRequest->created_at),
                    'member' => [
                        'customer_id' => $bookingRequest->member->customer_id,
                        'name' => $bookingRequest->member->user?->name,
                    ],
                    'plan' => $bookingRequest->member->membershipPlan?->name,
                    'quote' => $now,
                    'blocked_reason' => $blocked,
                ];
            });

        $history = CurrentRateBookingRequest::with(['member.user', 'decidedBy'])
            ->where('status', '!=', 'pending')
            ->orderByDesc('decided_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (CurrentRateBookingRequest $bookingRequest): array => [
                'id' => $bookingRequest->id,
                'status' => $bookingRequest->status,
                'requested_at' => Dates::date($bookingRequest->created_at),
                'decided_at' => Dates::date($bookingRequest->decided_at),
                'decided_by' => $bookingRequest->decidedBy?->name,
                'cancel_message' => $bookingRequest->cancel_message,
                'member' => [
                    'customer_id' => $bookingRequest->member->customer_id,
                    'name' => $bookingRequest->member->user?->name,
                ],
            ]);

        return Inertia::render('super-admin/rate-booking-requests', [
            'pending' => $pending,
            'history' => $history,
        ]);
    }

    public function approve(Request $request, CurrentRateBookingRequest $bookingRequest, DecideCurrentRateBookingRequest $decide): RedirectResponse
    {
        $data = $request->validate([
            'metal_rate_id' => ['required', 'integer'],
            'paid_installments' => ['required', 'integer', 'min:0'],
        ]);

        $decide->approve($bookingRequest, $request->user(), (int) $data['metal_rate_id'], (int) $data['paid_installments']);

        return back()->with('status', 'Booking approved — the member\'s remaining EMIs are updated.');
    }

    public function cancel(Request $request, CurrentRateBookingRequest $bookingRequest, DecideCurrentRateBookingRequest $decide): RedirectResponse
    {
        $data = $request->validate([
            'cancel_message' => ['required', 'string', 'max:500'],
        ]);

        $decide->cancel($bookingRequest, $request->user(), $data['cancel_message']);

        return back()->with('status', 'Request cancelled — the member will see your message.');
    }
}

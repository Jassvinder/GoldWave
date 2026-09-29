<?php

namespace App\Http\Controllers\Member;

use App\Actions\Emi\QuoteCurrentRateBooking;
use App\Actions\Emi\RequestCurrentRateBooking;
use App\Http\Controllers\Controller;
use App\Models\CurrentRateBookingRequest;
use App\Models\EmiSchedule;
use App\Models\Member;
use App\Models\ProductBenefit;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md M05 — current plan + product benefit, and the "Book at Current Rate" action (DOMAIN_LOGIC.md §3, §3.0). */
class MembershipController extends Controller
{
    public function show(Request $request, QuoteCurrentRateBooking $quote): Response
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        $member->load(['membershipPlan', 'productBenefits.metalRate', 'productBenefits.store']);

        $schedule = $member->emiSchedule()->first();
        $isCurrentRate = $schedule?->rate_booking_method === 'current_rate';
        $pendingRequest = $schedule
            ? CurrentRateBookingRequest::where('emi_schedule_id', $schedule->id)->where('status', 'pending')->latest('id')->first()
            : null;

        return Inertia::render('member/membership', [
            'plan' => $member->membershipPlan ? [
                'code' => $member->membershipPlan->code,
                'name' => $member->membershipPlan->name,
                'amount' => $member->membershipPlan->amount,
                'installment_count' => $member->membershipPlan->installment_count,
                'product_category' => $member->membershipPlan->product_category,
                // T-116 — a Future Rate member has no weight yet (it depends on the rate at delivery); the weight is
                // shown only once booked at the Current Rate. One-time plans have no fixed weight at all.
                'fixed_weight_grams' => $isCurrentRate ? $member->membershipPlan->fixed_weight_grams : null,
            ] : null,
            'rate_booking' => $schedule ? $this->rateBooking($schedule) : null,
            // T-166 — while a request waits for Super Admin, the page shows that instead of the button.
            'pending_booking_request' => $pendingRequest ? ['requested_at' => Dates::date($pendingRequest->created_at)] : null,
            'current_rate_quote' => $schedule && ! $pendingRequest ? $this->quoteFor($member, $quote) : null,
            'product_benefits' => $member->productBenefits->map($this->mapBenefit(...))->all(),
        ]);
    }

    /** T-166 (28-09-2026) — files a request; Super Admin approves it at the approval day's rate. */
    public function bookCurrentRate(Request $request, RequestCurrentRateBooking $action): RedirectResponse
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        $action($member);

        return redirect()->route('member.membership.show')
            ->with('status', 'Request sent. Super Admin will confirm your Current Rate booking — until then your EMIs stay as they are.');
    }

    /** @return array<string, mixed> */
    private function rateBooking(EmiSchedule $schedule): array
    {
        $isCurrentRate = $schedule->rate_booking_method === 'current_rate';
        // T-167 — on Current Rate every EMI is smaller than the one before, so show the next and the last one.
        $unpaid = $isCurrentRate ? $schedule->installments()->where('status', '!=', 'paid')->orderBy('installment_no')->pluck('amount') : collect();

        return [
            'method' => $schedule->rate_booking_method,
            'installment_amount' => $schedule->installment_amount,
            'next_installment_amount' => $unpaid->first(),
            'last_installment_amount' => $unpaid->last(),
            'total_installments' => $schedule->total_installments,
            'rate_per_gram' => $isCurrentRate ? $schedule->rate_per_gram_at_booking : null,
            'fixed_weight_grams' => $isCurrentRate ? $schedule->fixed_weight_grams : null,
            'booked_at' => $isCurrentRate ? Dates::date($schedule->current_rate_booked_at) : null,
        ];
    }

    /**
     * The popup figures, or null when this member cannot book (already Current Rate, nothing left to pay, a payment awaiting
     * confirmation, ...) — the page then simply has no button.
     *
     * @return array<string, mixed>|null
     */
    private function quoteFor(Member $member, QuoteCurrentRateBooking $quote): ?array
    {
        try {
            $q = $quote($member);
        } catch (ValidationException) {
            return null;
        }

        return [
            'metal' => $q['metal'],
            'fixed_weight_grams' => $q['fixed_weight_grams'],
            'metal_rate_id' => $q['metal_rate_id'],
            'rate_per_gram' => $q['rate_per_gram'],
            'metal_value' => $q['metal_value'],
            'making_charge_percent' => $q['making_charge_percent'],
            'making_charges' => $q['making_charges'],
            'total_value' => $q['total_value'],
            'paid_installments' => $q['paid_installments'],
            'paid_amount' => $q['paid_amount'],
            'remaining_value' => $q['remaining_value'],
            'pending_installments' => $q['pending_installments'],
            'maintenance_cost' => $q['maintenance_cost'],
            'installment_amount' => $q['installment_amount'],
            'last_installment_amount' => $q['last_installment_amount'],
            'total_maintenance' => $q['total_maintenance'],
            'total_remaining_payable' => $q['total_remaining_payable'],
            'current_installment_amount' => $q['schedule']->installment_amount,
        ];
    }

    /** @return array<string, mixed> */
    private function mapBenefit(ProductBenefit $benefit): array
    {
        return [
            'metal' => $benefit->metal,
            'rate_per_gram_at_entry' => $benefit->rate_per_gram_at_entry,
            'entry_date' => Dates::date($benefit->entry_date),
            'delivered_at' => Dates::date($benefit->delivered_at),
            'store_name' => $benefit->store?->name,
        ];
    }
}

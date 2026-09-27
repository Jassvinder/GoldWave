<?php

namespace App\Http\Controllers\Member;

use App\Actions\Emi\BookCurrentRate;
use App\Actions\Emi\QuoteCurrentRateBooking;
use App\Http\Controllers\Controller;
use App\Http\Requests\Emi\BookCurrentRateRequest;
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
            'current_rate_quote' => $schedule ? $this->quoteFor($member, $quote) : null,
            'product_benefits' => $member->productBenefits->map($this->mapBenefit(...))->all(),
        ]);
    }

    public function bookCurrentRate(BookCurrentRateRequest $request, BookCurrentRate $action): RedirectResponse
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        $action($member, $request->integer('metal_rate_id'), $request->integer('paid_installments'));

        return redirect()->route('member.membership.show')
            ->with('status', 'Booked at the Current Rate. Your remaining EMIs have been updated.');
    }

    /** @return array<string, mixed> */
    private function rateBooking(EmiSchedule $schedule): array
    {
        $isCurrentRate = $schedule->rate_booking_method === 'current_rate';

        return [
            'method' => $schedule->rate_booking_method,
            'installment_amount' => $schedule->installment_amount,
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
            'total_value' => $q['total_value'],
            'paid_installments' => $q['paid_installments'],
            'paid_amount' => $q['paid_amount'],
            'remaining_value' => $q['remaining_value'],
            'pending_installments' => $q['pending_installments'],
            'maintenance_cost' => $q['maintenance_cost'],
            'installment_amount' => $q['installment_amount'],
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

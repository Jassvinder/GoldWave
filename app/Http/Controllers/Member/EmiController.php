<?php

namespace App\Http\Controllers\Member;

use App\Actions\Emi\QuoteFullEmiPayment;
use App\Actions\Payments\InitiateEmiInstallmentPayment;
use App\Actions\Payments\InitiateFullEmiPayment;
use App\Contracts\PaymentGatewayContract;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\PayEmiInstallmentRequest;
use App\Models\CurrentRateBookingRequest;
use App\Models\EmiInstallment;
use App\Models\EmiSchedule;
use App\Models\Member;
use App\Models\StoreEmiBooking;
use App\Services\Payments\PaymentModes;
use App\Services\RuleVersionService;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DOMAIN_LOGIC.md §5/§6/§7.3/§10 — Member's own EMI schedule and the
 * recurring installment "Payment In" flow (T-005, polished to the full M06
 * spec in T-015: paid date/reference/mode columns and the Pair/Reward
 * eligibility indicator, both deferred at T-005 since Level Income/Pair
 * eligibility rules didn't exist yet).
 */
class EmiController extends Controller
{
    public function __construct(
        private readonly RuleVersionService $rules,
        private readonly QuoteFullEmiPayment $fullPaymentQuote,
    ) {}

    public function index(Request $request): Response
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        $schedule = $member->emiSchedule()->with(['installments' => function ($query) {
            $query->orderBy('installment_no');
        }, 'installments.payment', 'membershipPlan'])->first();

        $paidCount = $schedule?->installments->where('status', 'paid')->count() ?? 0;
        $plan = $schedule?->membershipPlan;
        $requiredPairEmis = $plan ? (int) ($this->rules->value('pair_qualification_emis', [])[$plan->code] ?? PHP_INT_MAX) : null;
        $pairEligible = $requiredPairEmis !== null && $paidCount >= $requiredPairEmis;

        return Inertia::render('member/emi', [
            'payment_options' => PaymentModes::forPage(),
            'schedule' => $schedule ? [
                'rate_booking_method' => $schedule->rate_booking_method,
                'installment_amount' => $schedule->installment_amount,
                'total_installments' => $schedule->total_installments,
                // T-116 — set only once booked at the Current Rate.
                'booked_at' => Dates::date($schedule->current_rate_booked_at),
                'rate_per_gram' => $schedule->rate_booking_method === 'current_rate' ? $schedule->rate_per_gram_at_booking : null,
                'fixed_weight_grams' => $schedule->rate_booking_method === 'current_rate' ? $schedule->fixed_weight_grams : null,
                'pending_installments' => $schedule->installments->where('status', '!=', 'paid')->count(),
            ] : null,
            'installments' => $schedule?->installments->map($this->mapInstallment(...))->all() ?? [],
            'pair_eligibility' => $plan?->isEmiPlan() ? [
                'required_emis' => $requiredPairEmis,
                'completed_emis' => $paidCount,
                'eligible' => $pairEligible,
            ] : null,
            'booking_request' => $schedule ? $this->bookingRequestStatus($schedule->id) : null,
            // T-182 — earnings held while an EMI is overdue; paying the overdue EMI(s) releases them.
            'held_earnings' => $member->heldEarnings(),
            'full_payment' => $this->fullPaymentOffer($member, $paidCount, $schedule?->total_installments),
            // T-185a — the member's Repurchase on EMI, if any.
            'store_emi' => $this->storeEmi($member),
        ]);
    }

    /**
     * T-185a (DOMAIN_LOGIC.md §16.13) — the member's latest Repurchase on EMI: the piece, its status, and once approved
     * its installments (paid like any EMI) and the "Pay All Remaining EMIs" offer. Nothing once it was delivered.
     *
     * @return array<string, mixed>|null
     */
    private function storeEmi(Member $member): ?array
    {
        $booking = $member->storeEmiBookings()->with(['store', 'emiSchedule.installments.payment'])->latest('id')->first();

        if ($booking === null || $booking->status === 'delivered') {
            return null;
        }

        $schedule = $booking->emiSchedule;
        $installments = $schedule?->installments->sortBy('installment_no')->values();
        $paid = $installments?->where('status', 'paid')->count() ?? 0;

        return [
            'id' => $booking->id,
            'status' => $booking->status,
            'item_name' => $booking->item_name,
            'metal' => $booking->metal,
            'weight_grams' => (float) $booking->weight_grams,
            'installment_count' => $booking->installment_count,
            'store' => $booking->store->name,
            'requested_at' => Dates::date($booking->created_at),
            'decided_at' => Dates::date($booking->decided_at),
            'cancel_message' => $booking->cancel_message,
            // T-185b — once broken: the silver owed for the principal paid.
            'broken_at' => Dates::date($booking->broken_at),
            'principal_paid' => $booking->principal_paid,
            'silver_grams_owed' => $booking->silver_grams_owed,
            'silver_rate_per_gram' => $booking->silver_rate_per_gram,
            'rate_per_gram' => $schedule?->rate_per_gram_at_booking,
            'installments' => $installments?->map($this->mapInstallment(...))->all() ?? [],
            'full_payment' => $booking->status === 'active'
                ? $this->fullPaymentOffer($member, $paid, $installments?->whereNotIn('status', ['cancelled'])->count(), $schedule)
                : null,
        ];
    }

    /**
     * T-184 — the "Pay All Remaining EMIs" card: the amount now (on Current Rate without maintenance), or why it is not
     * available right now (a payment is still waiting). Null when nothing is left to pay.
     *
     * @return array{count: int, amount: float|null, regular_total: float|null, saving: float|null, blocked_reason: string|null}|null
     */
    private function fullPaymentOffer(Member $member, int $paidCount, ?int $totalInstallments, ?EmiSchedule $schedule = null): ?array
    {
        if ($totalInstallments === null || $paidCount >= $totalInstallments) {
            return null;
        }

        try {
            $quote = ($this->fullPaymentQuote)($member, $schedule);
        } catch (ValidationException $e) {
            return [
                'count' => $totalInstallments - $paidCount,
                'amount' => null,
                'regular_total' => null,
                'saving' => null,
                'blocked_reason' => (string) collect($e->errors())->flatten()->first(),
            ];
        }

        return [
            'count' => $quote['installments']->count(),
            'amount' => $quote['amount'],
            'regular_total' => $quote['regular_total'],
            'saving' => $quote['saving'],
            'blocked_reason' => null,
        ];
    }

    /**
     * T-166 (28-09-2026) — the member's latest Current Rate booking request, so the page can say it is waiting for
     * approval or show Super Admin's cancel message. Nothing once it was approved (the schedule itself shows that).
     *
     * @return array<string, string|null>|null
     */
    private function bookingRequestStatus(int $scheduleId): ?array
    {
        $latest = CurrentRateBookingRequest::where('emi_schedule_id', $scheduleId)->latest('id')->first();

        if ($latest === null || $latest->status === 'approved') {
            return null;
        }

        return [
            'status' => $latest->status,
            'requested_at' => Dates::date($latest->created_at),
            'decided_at' => Dates::date($latest->decided_at),
            'cancel_message' => $latest->cancel_message,
        ];
    }

    /** @return array<string, mixed> */
    private function mapInstallment(EmiInstallment $installment): array
    {
        return [
            'id' => $installment->id,
            'installment_no' => $installment->installment_no,
            'due_date' => Dates::date($installment->due_date),
            'amount' => $installment->amount,
            'status' => $installment->status,
            'paid_at' => Dates::date($installment->payment?->paid_at),
            'payment_reference' => $installment->payment?->provider_reference,
            'payment_mode' => $installment->payment?->mode,
        ];
    }

    public function pay(
        PayEmiInstallmentRequest $request,
        EmiInstallment $installment,
        InitiateEmiInstallmentPayment $action,
        PaymentGatewayContract $gateway,
    ): RedirectResponse {
        $member = $request->user()->member;

        abort_if($member === null, 404);
        abort_if($installment->emiSchedule?->member_id !== $member->id, 403);

        $payment = $action($member, $installment, $request->string('mode')->toString());
        PaymentModes::attachUpiProof($payment, $request);

        if ($payment->mode === 'online') {
            // T-137 — through the bound gateway (Razorpay checkout, or the dev stand-in when no keys are set).
            return redirect($gateway->createIntent($payment)['redirect_url']);
        }

        return redirect()->route('member.emi.index')
            ->with('status', self::submittedMessage($payment->mode, 'EMI payment'));
    }

    /** T-184 — "Pay All Remaining EMIs" (DOMAIN_LOGIC.md §5 point 9), the same Online / Cash flow as a single EMI. */
    public function payAll(PayEmiInstallmentRequest $request, InitiateFullEmiPayment $action, PaymentGatewayContract $gateway): RedirectResponse
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        $payment = $action($member, $request->string('mode')->toString());
        PaymentModes::attachUpiProof($payment, $request);

        if ($payment->mode === 'online') {
            return redirect($gateway->createIntent($payment)['redirect_url']);
        }

        return redirect()->route('member.emi.index')
            ->with('status', self::submittedMessage($payment->mode, 'Payment for all remaining EMIs'));
    }

    /** T-185a — "Pay All Remaining EMIs" for the member's own store Repurchase on EMI (principal only, Current Rate). */
    public function payAllStore(PayEmiInstallmentRequest $request, StoreEmiBooking $booking, InitiateFullEmiPayment $action, PaymentGatewayContract $gateway): RedirectResponse
    {
        $member = $request->user()->member;

        abort_if($member === null || $booking->member_id !== $member->id || $booking->emiSchedule === null, 404);

        $payment = $action($member, $request->string('mode')->toString(), $booking->emiSchedule);
        PaymentModes::attachUpiProof($payment, $request);

        if ($payment->mode === 'online') {
            return redirect($gateway->createIntent($payment)['redirect_url']);
        }

        return redirect()->route('member.emi.index')
            ->with('status', self::submittedMessage($payment->mode, 'Payment for all remaining Repurchase EMIs'));
    }

    /** T-196 — what the member sees after a Cash or GPay/UPI payment is submitted for approval. */
    private static function submittedMessage(string $mode, string $what): string
    {
        $how = $mode === 'upi' ? 'GPay/UPI' : 'Cash';

        return "{$what} ({$how}) submitted — awaiting approval by the company.";
    }
}

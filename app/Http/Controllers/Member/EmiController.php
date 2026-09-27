<?php

namespace App\Http\Controllers\Member;

use App\Actions\Payments\InitiateEmiInstallmentPayment;
use App\Contracts\PaymentGatewayContract;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\PayEmiInstallmentRequest;
use App\Models\EmiInstallment;
use App\Services\RuleVersionService;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function __construct(private readonly RuleVersionService $rules) {}

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
        ]);
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

        if ($payment->mode === 'online') {
            // T-137 — through the bound gateway (Razorpay checkout, or the dev stand-in when no keys are set).
            return redirect($gateway->createIntent($payment)['redirect_url']);
        }

        return redirect()->route('member.emi.index')
            ->with('status', 'Cash payment submitted — awaiting Super Admin confirmation.');
    }
}

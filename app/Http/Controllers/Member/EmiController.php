<?php

namespace App\Http\Controllers\Member;

use App\Actions\Payments\InitiateEmiInstallmentPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\PayEmiInstallmentRequest;
use App\Models\EmiInstallment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DOMAIN_LOGIC.md §5/§10 — Member's own EMI schedule and the recurring
 * installment "Payment In" flow (T-005). The full designed M06 EMI page
 * (`Docs/INSTRUCTIONS.md`) is T-015's job; this is the working backend +
 * minimal page T-005 needs to generate/pay installments end-to-end, the same
 * relationship T-003's registration pages had to the dev-simulate flow.
 */
class EmiController extends Controller
{
    public function index(Request $request): Response
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        $schedule = $member->emiSchedule()->with(['installments' => function ($query) {
            $query->orderBy('installment_no');
        }])->first();

        return Inertia::render('member/emi', [
            'schedule' => $schedule ? [
                'rate_booking_method' => $schedule->rate_booking_method,
                'installment_amount' => $schedule->installment_amount,
                'total_installments' => $schedule->total_installments,
            ] : null,
            'installments' => $schedule?->installments->map(fn (EmiInstallment $installment): array => [
                'id' => $installment->id,
                'installment_no' => $installment->installment_no,
                'due_date' => Carbon::parse($installment->due_date)->toDateString(),
                'amount' => $installment->amount,
                'status' => $installment->status,
            ]) ?? [],
        ]);
    }

    public function pay(
        PayEmiInstallmentRequest $request,
        EmiInstallment $installment,
        InitiateEmiInstallmentPayment $action,
    ): RedirectResponse {
        $member = $request->user()->member;

        abort_if($member === null, 404);
        abort_if($installment->emiSchedule?->member_id !== $member->id, 403);

        $payment = $action($member, $installment, $request->string('mode')->toString());

        if ($payment->mode === 'online') {
            return redirect()->route('payments.dev-simulate.show', $payment);
        }

        return redirect()->route('member.emi.index')
            ->with('status', 'Cash payment submitted — awaiting Super Admin confirmation.');
    }
}

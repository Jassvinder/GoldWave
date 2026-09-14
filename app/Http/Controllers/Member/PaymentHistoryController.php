<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Support\Dates;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md M07 — Payment In transaction history. */
class PaymentHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        $payments = $member->payments()
            ->with('emiInstallment')
            ->orderByDesc('id')
            ->get()
            ->map($this->mapPayment(...));

        return Inertia::render('member/payment-history', [
            'payments' => $payments,
        ]);
    }

    /** @return array<string, mixed> */
    private function mapPayment(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'type' => $payment->type,
            'installment_no' => $payment->emiInstallment?->installment_no,
            'amount' => $payment->amount,
            'mode' => $payment->mode,
            'status' => $payment->status,
            'provider_reference' => $payment->provider_reference,
            'paid_at' => Dates::date($payment->paid_at),
        ];
    }
}

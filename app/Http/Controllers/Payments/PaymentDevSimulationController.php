<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Registration\RegistrationController;
use App\Models\Payment;
use App\Services\Payments\FakePaymentGateway;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dev/local-only stand-in for a real gateway's hosted checkout page — see
 * FakePaymentGateway and ARCHITECTURE.md's "payment gateway deliberately not
 * chosen yet." Only registered outside production (routes/payments.php).
 * The signed fields below are POSTed by the page straight to the real
 * `payments.webhook` endpoint — this exercises the exact same verification
 * code path a genuine gateway webhook would use.
 */
class PaymentDevSimulationController extends Controller
{
    public function show(Payment $payment, FakePaymentGateway $gateway): Response
    {
        $member = $payment->member()->firstOrFail();
        $reference = 'FAKE-'.$payment->id.'-'.Str::random(10);
        $signature = $gateway->sign($payment->id, $reference);

        // Registration payments happen pre-login (§2.2 — no login before activation), so their
        // post-payment status page needs the signed public URL. EMI-installment payments happen
        // from an authenticated member session, so a plain named route is enough (T-005).
        $statusUrl = $payment->type === 'registration'
            ? RegistrationController::signedStatusUrl($member)
            : route('member.emi.index');

        return Inertia::render('payments/dev-simulate', [
            'payment' => $payment,
            'webhookUrl' => route('payments.webhook'),
            'statusUrl' => $statusUrl,
            'fields' => [
                'payment_id' => $payment->id,
                'reference' => $reference,
                'signature' => $signature,
            ],
        ]);
    }
}

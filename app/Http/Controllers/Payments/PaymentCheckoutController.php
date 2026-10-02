<?php

namespace App\Http\Controllers\Payments;

use App\Actions\Payments\ConfirmOnlinePayment;
use App\Contracts\PaymentGatewayContract;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\RazorpayGateway;
use App\Support\PaymentReturnUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-137 — our own page around Razorpay Checkout (DOMAIN_LOGIC.md §10.1). `show` is reached only through a signed,
 * expiring URL (so a payment id cannot be enumerated); `verify` receives what Checkout returns and confirms the payment
 * only after `RazorpayGateway::verifyReturn()` has verified it server-side — the browser's success callback alone never
 * activates anything.
 */
class PaymentCheckoutController extends Controller
{
    public function show(Payment $payment, PaymentGatewayContract $gateway): Response|RedirectResponse
    {
        abort_unless($payment->mode === 'online', 404);

        if ($payment->status === 'paid') {
            return redirect(PaymentReturnUrl::for($payment));
        }

        abort_unless($payment->status === 'pending', 404);

        if (! $gateway instanceof RazorpayGateway) {
            // Local development without Razorpay keys: the fake gateway's stand-in checkout page.
            abort_if(app()->isProduction(), 404);

            return redirect()->route('payments.dev-simulate.show', $payment);
        }

        $user = $payment->member()->firstOrFail()->user;
        $razorpay = null;
        $error = null;

        try {
            $razorpay = $gateway->checkoutOptions($payment);
        } catch (ValidationException $e) {
            $error = collect($e->errors())->flatten()->first();
        }

        return Inertia::render('payments/razorpay-checkout', [
            'payment' => ['id' => $payment->id, 'amount' => $payment->amount, 'type' => $payment->type],
            'razorpay' => $razorpay,
            'error' => $error,
            'prefill' => [
                'name' => $user?->name,
                'email' => $user?->email,
                'contact' => $user?->mobile,
            ],
            'verifyUrl' => route('payments.razorpay.verify', $payment),
            'statusUrl' => PaymentReturnUrl::for($payment),
        ]);
    }

    public function verify(Request $request, Payment $payment, PaymentGatewayContract $gateway, ConfirmOnlinePayment $confirm): RedirectResponse
    {
        abort_unless($payment->mode === 'online' && $gateway instanceof RazorpayGateway, 404);

        if ($payment->status === 'paid') {
            return redirect(PaymentReturnUrl::for($payment));
        }

        $data = $request->validate([
            'razorpay_payment_id' => ['required', 'string', 'max:100'],
            'razorpay_order_id' => ['required', 'string', 'max:100'],
            'razorpay_signature' => ['required', 'string', 'max:200'],
        ]);

        $result = $gateway->verifyReturn($payment, $data);

        if (! $result->verified) {
            return back()->withErrors(['payment' => $result->message ?? 'The payment could not be verified.']);
        }

        $confirm($payment, $result->providerReference, $result->payload);

        return redirect(PaymentReturnUrl::for($payment));
    }
}

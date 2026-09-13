<?php

namespace App\Services\Payments;

use App\Contracts\PaymentCallbackResult;
use App\Contracts\PaymentGatewayContract;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Default binding until a real payment gateway vendor is chosen
 * (ARCHITECTURE.md). Signs the fake intent/callback with an HMAC over the app
 * key so the exact same `verifyCallback()` code path a real provider's
 * webhook would use is exercised end-to-end in dev/tests via
 * `routes/payments.php`'s dev-simulate route.
 */
class FakePaymentGateway implements PaymentGatewayContract
{
    public function createIntent(Payment $payment): array
    {
        $reference = 'FAKE-'.$payment->id.'-'.Str::random(10);

        return [
            'reference' => $reference,
            'redirect_url' => route('payments.dev-simulate.show', $payment),
        ];
    }

    public function verifyCallback(Request $request): PaymentCallbackResult
    {
        $paymentId = (int) $request->input('payment_id');
        $reference = (string) $request->input('reference');
        $signature = (string) $request->input('signature');

        $expected = $this->sign($paymentId, $reference);
        $verified = $paymentId > 0 && $reference !== '' && hash_equals($expected, $signature);

        return new PaymentCallbackResult(
            verified: $verified,
            paymentId: $paymentId,
            providerReference: $reference,
            payload: $request->all(),
        );
    }

    public function sign(int $paymentId, string $reference): string
    {
        return hash_hmac('sha256', $paymentId.'|'.$reference, config('app.key'));
    }
}

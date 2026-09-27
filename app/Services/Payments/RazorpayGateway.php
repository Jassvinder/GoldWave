<?php

namespace App\Services\Payments;

use App\Contracts\PaymentCallbackResult;
use App\Contracts\PaymentGatewayContract;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * T-137 — Razorpay (DOMAIN_LOGIC.md §10.1, ARCHITECTURE.md "Payment gateway"). Plain Laravel `Http` client, no SDK.
 *
 * - `createIntent()` creates (or reuses) the Razorpay Order for a pending payment and returns a signed, expiring link
 *   to our own checkout page, which opens Razorpay Checkout.
 * - `verifyReturn()` is the return path: the signature Checkout hands back is checked, then the payment is fetched
 *   from Razorpay itself (and captured if it is merely authorized) — the browser's word alone never confirms anything.
 * - `verifyCallback()` is the webhook path: raw-body HMAC with the webhook secret; only `order.paid` /
 *   `payment.captured` confirm, everything else is acknowledged and ignored.
 *
 * Both paths end in the same idempotent `ConfirmOnlinePayment`. Amount is always compared in paise against our own
 * `payments` row; a mismatch is refused and logged, never confirmed.
 */
class RazorpayGateway implements PaymentGatewayContract
{
    private const CURRENCY = 'INR';

    private const PAID_EVENTS = ['order.paid', 'payment.captured'];

    public function createIntent(Payment $payment): array
    {
        return [
            'reference' => $this->ensureOrder($payment),
            'redirect_url' => self::checkoutUrl($payment),
        ];
    }

    /** Signed, expiring link to our checkout page — a payment id alone can never open it. */
    public static function checkoutUrl(Payment $payment): string
    {
        return URL::temporarySignedRoute('payments.checkout.show', now()->addHours(2), ['payment' => $payment->id]);
    }

    /**
     * What the checkout page needs to open Razorpay Checkout.
     *
     * @return array{key: string, order_id: string, amount: int, currency: string, name: string}
     */
    public function checkoutOptions(Payment $payment): array
    {
        return [
            'key' => $this->keyId(),
            'order_id' => $this->ensureOrder($payment),
            'amount' => $this->paise($payment),
            'currency' => self::CURRENCY,
            'name' => (string) config('services.razorpay.business_name', 'GoldWave'),
        ];
    }

    /** One Razorpay Order per pending payment, reused for retries; its id is how a webhook finds our payment. */
    public function ensureOrder(Payment $payment): string
    {
        if ($payment->gateway_order_id !== null) {
            return $payment->gateway_order_id;
        }

        return DB::transaction(function () use ($payment): string {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->gateway_order_id !== null) {
                $payment->gateway_order_id = $locked->gateway_order_id;

                return $locked->gateway_order_id;
            }

            try {
                $response = $this->client()->post('/orders', [
                    'amount' => $this->paise($locked),
                    'currency' => self::CURRENCY,
                    'receipt' => 'GW-'.$locked->id,
                    'notes' => ['payment_id' => (string) $locked->id, 'type' => $locked->type],
                ]);
            } catch (ConnectionException $e) {
                Log::error('Razorpay order creation could not reach Razorpay', ['payment_id' => $locked->id, 'error' => $e->getMessage()]);

                throw $this->unavailable();
            }

            $orderId = $response->json('id');

            if ($response->failed() || ! is_string($orderId) || $orderId === '') {
                Log::error('Razorpay order creation failed', [
                    'payment_id' => $locked->id,
                    'status' => $response->status(),
                    'error' => $response->json('error.description'),
                ]);

                throw $this->unavailable();
            }

            $locked->update(['gateway_order_id' => $orderId]);
            $payment->gateway_order_id = $orderId;

            return $orderId;
        });
    }

    /**
     * Return path — verifies what Checkout handed back to the browser: the payment must belong to our order, carry a
     * valid signature, and be confirmed as captured (capturing it first if only authorized) by Razorpay's own API with
     * the same order, amount and currency as our payment.
     *
     * @param  array<string, mixed>  $data  razorpay_payment_id, razorpay_order_id, razorpay_signature
     */
    public function verifyReturn(Payment $payment, array $data): PaymentCallbackResult
    {
        $orderId = (string) ($data['razorpay_order_id'] ?? '');
        $razorpayPaymentId = (string) ($data['razorpay_payment_id'] ?? '');
        $signature = (string) ($data['razorpay_signature'] ?? '');

        $refuse = fn (string $message): PaymentCallbackResult => new PaymentCallbackResult(false, $payment->id, $razorpayPaymentId, [], 'ignored', $message);

        if ($orderId === '' || $razorpayPaymentId === '' || $signature === '') {
            return $refuse('The payment details were incomplete.');
        }

        if ($payment->gateway_order_id === null || ! hash_equals($payment->gateway_order_id, $orderId)) {
            return $refuse('This payment does not belong to this order.');
        }

        $expected = hash_hmac('sha256', $orderId.'|'.$razorpayPaymentId, $this->keySecret());

        if (! hash_equals($expected, $signature)) {
            return $refuse('The payment signature could not be verified.');
        }

        $unconfirmed = 'We could not confirm your payment with the bank right now. If money was deducted it will be confirmed automatically shortly.';

        try {
            $fetched = $this->client()->get("/payments/{$razorpayPaymentId}");
        } catch (ConnectionException) {
            return $refuse($unconfirmed);
        }

        if ($fetched->failed()) {
            return $refuse($unconfirmed);
        }

        $entity = (array) $fetched->json();

        if (($entity['order_id'] ?? null) !== $orderId
            || (int) ($entity['amount'] ?? -1) !== $this->paise($payment)
            || ($entity['currency'] ?? null) !== self::CURRENCY) {
            Log::warning('Razorpay payment did not match our order', ['payment_id' => $payment->id, 'razorpay_payment_id' => $razorpayPaymentId]);

            return $refuse('The payment details did not match this order.');
        }

        $status = $entity['status'] ?? null;

        if ($status === 'authorized') {
            try {
                $captured = $this->client()->post("/payments/{$razorpayPaymentId}/capture", [
                    'amount' => $this->paise($payment),
                    'currency' => self::CURRENCY,
                ]);
            } catch (ConnectionException) {
                return $refuse($unconfirmed);
            }

            $status = $captured->successful() ? $captured->json('status') : null;
        }

        if ($status !== 'captured') {
            return $refuse('The payment was not completed.');
        }

        return new PaymentCallbackResult(
            verified: true,
            paymentId: $payment->id,
            providerReference: $razorpayPaymentId,
            payload: [
                'razorpay_order_id' => $orderId,
                'razorpay_payment_id' => $razorpayPaymentId,
                'razorpay_signature' => $signature,
                'method' => $entity['method'] ?? null,
                'status' => 'captured',
            ],
        );
    }

    /** Webhook path — see the class docblock. */
    public function verifyCallback(Request $request): PaymentCallbackResult
    {
        $secret = (string) config('services.razorpay.webhook_secret');
        $signature = (string) $request->header('X-Razorpay-Signature');
        $body = $request->getContent();

        if ($secret === '' || $signature === '' || ! hash_equals(hash_hmac('sha256', $body, $secret), $signature)) {
            return new PaymentCallbackResult(false, 0, '', [], 'ignored', 'Invalid webhook signature.');
        }

        /** @var array<string, mixed> $event */
        $event = (array) json_decode($body, true);
        $name = (string) ($event['event'] ?? '');

        if (! in_array($name, self::PAID_EVENTS, true)) {
            return new PaymentCallbackResult(true, 0, '', $event, 'ignored', "Event {$name} needs no action.");
        }

        /** @var array<string, mixed> $entity */
        $entity = (array) data_get($event, 'payload.payment.entity', []);
        $orderId = (string) ($entity['order_id'] ?? '');
        $razorpayPaymentId = (string) ($entity['id'] ?? '');

        $payment = $orderId !== ''
            ? Payment::where('gateway_order_id', $orderId)->where('mode', 'online')->first()
            : null;

        if ($payment === null || $razorpayPaymentId === '') {
            return new PaymentCallbackResult(true, 0, $razorpayPaymentId, $event, 'ignored', 'Order is not one of ours.');
        }

        if ((int) ($entity['amount'] ?? -1) !== $this->paise($payment)
            || ($entity['currency'] ?? null) !== self::CURRENCY
            || ($entity['status'] ?? null) !== 'captured') {
            Log::critical('Razorpay webhook did not match our payment', ['payment_id' => $payment->id, 'razorpay_payment_id' => $razorpayPaymentId]);

            return new PaymentCallbackResult(true, $payment->id, $razorpayPaymentId, $event, 'ignored', 'Webhook amount/currency/status did not match.');
        }

        return new PaymentCallbackResult(true, $payment->id, $razorpayPaymentId, $event);
    }

    private function paise(Payment $payment): int
    {
        return (int) round((float) $payment->amount * 100);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.razorpay.base_url'))
            ->withBasicAuth($this->keyId(), $this->keySecret())
            ->acceptJson()
            ->asJson()
            ->timeout(15);
    }

    private function keyId(): string
    {
        $key = (string) config('services.razorpay.key_id');

        if ($key === '') {
            throw new RuntimeException('RAZORPAY_KEY_ID is not configured.');
        }

        return $key;
    }

    private function keySecret(): string
    {
        $secret = (string) config('services.razorpay.key_secret');

        if ($secret === '') {
            throw new RuntimeException('RAZORPAY_KEY_SECRET is not configured.');
        }

        return $secret;
    }

    private function unavailable(): ValidationException
    {
        return ValidationException::withMessages([
            'payment' => 'The online payment service is not reachable right now. Please try again in a few minutes.',
        ]);
    }
}

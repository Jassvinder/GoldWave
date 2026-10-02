<?php

namespace App\Contracts;

/**
 * What a gateway's inbound callback/webhook (or return-path verification) established.
 *
 * `verified` is about authenticity (a valid signature); `outcome` says what to do with it: `paid` confirms the payment,
 * `ignored` acknowledges a genuine but irrelevant event (e.g. Razorpay's `payment.failed`, or an order we do not know)
 * without changing anything. `message` is a human-readable reason for a refused/ignored result.
 */
final readonly class PaymentCallbackResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public bool $verified,
        public int $paymentId,
        public string $providerReference,
        public array $payload,
        public string $outcome = 'paid',
        public ?string $message = null,
    ) {}
}

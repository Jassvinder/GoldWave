<?php

namespace App\Contracts;

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
    ) {}
}

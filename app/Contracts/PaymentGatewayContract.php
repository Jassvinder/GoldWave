<?php

namespace App\Contracts;

use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * ARCHITECTURE.md: the payment gateway vendor is deliberately not chosen yet
 * (a cost/vendor decision for the user, not an architecture default). Every
 * Action depends on this interface, never on a concrete gateway class, so
 * swapping the bound implementation (see AppServiceProvider) is the only
 * change needed once a real provider is selected — no controller/Action code
 * changes.
 */
interface PaymentGatewayContract
{
    /**
     * Create a payment intent with the provider and return the data the
     * frontend needs to continue (redirect URL and/or provider reference).
     *
     * @return array{reference: string, redirect_url: string}
     */
    public function createIntent(Payment $payment): array;

    /**
     * Verify an inbound callback/webhook request's authenticity.
     */
    public function verifyCallback(Request $request): PaymentCallbackResult;
}

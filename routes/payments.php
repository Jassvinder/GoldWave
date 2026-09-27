<?php

use App\Http\Controllers\Payments\PaymentCheckoutController;
use App\Http\Controllers\Payments\PaymentDevSimulationController;
use App\Http\Controllers\Payments\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

// DOMAIN_LOGIC.md §10.1: public webhook endpoint, CSRF-exempted in bootstrap/app.php.
Route::post('payments/webhook', PaymentWebhookController::class)->name('payments.webhook');

// T-137 — Razorpay Checkout page (signed + expiring URL only, so a payment id cannot be enumerated) and the endpoint the
// page posts Checkout's result to. Verification happens server-side inside the gateway; the endpoint is throttled.
Route::get('payments/{payment}/checkout', [PaymentCheckoutController::class, 'show'])
    ->middleware('signed')
    ->name('payments.checkout.show');
Route::post('payments/{payment}/razorpay/verify', [PaymentCheckoutController::class, 'verify'])
    ->middleware('throttle:20,1')
    ->name('payments.razorpay.verify');

// Dev-only stand-in for a real gateway's hosted checkout (FakePaymentGateway) — never registered in production.
if (! app()->isProduction()) {
    Route::get('payments/{payment}/dev-simulate', [PaymentDevSimulationController::class, 'show'])
        ->name('payments.dev-simulate.show');
}

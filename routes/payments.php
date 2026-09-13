<?php

use App\Http\Controllers\Payments\PaymentDevSimulationController;
use App\Http\Controllers\Payments\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

// DOMAIN_LOGIC.md §10.1: public webhook endpoint, CSRF-exempted in bootstrap/app.php.
Route::post('payments/webhook', PaymentWebhookController::class)->name('payments.webhook');

// Dev-only stand-in for a real gateway's hosted checkout (FakePaymentGateway) — never registered in production.
if (! app()->isProduction()) {
    Route::get('payments/{payment}/dev-simulate', [PaymentDevSimulationController::class, 'show'])
        ->name('payments.dev-simulate.show');
}

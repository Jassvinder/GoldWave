<?php

namespace App\Http\Controllers\Payments;

use App\Actions\Payments\ConfirmOnlinePayment;
use App\Contracts\PaymentGatewayContract;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DOMAIN_LOGIC.md §10.1 — never activates on frontend success alone; only a
 * verified callback/webhook confirms a payment. CSRF-exempted in
 * bootstrap/app.php since a real gateway's webhook carries no Inertia/session
 * CSRF token — the HMAC signature (verified inside the gateway binding) is
 * the actual authenticity check.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGatewayContract $gateway, ConfirmOnlinePayment $action): JsonResponse
    {
        $result = $gateway->verifyCallback($request);

        if (! $result->verified) {
            return response()->json(['status' => 'invalid_signature'], 400);
        }

        $payment = Payment::where('id', $result->paymentId)->where('mode', 'online')->firstOrFail();

        $action($payment, $result->providerReference, $result->payload);

        return response()->json(['status' => 'ok']);
    }
}

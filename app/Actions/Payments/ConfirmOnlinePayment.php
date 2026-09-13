<?php

namespace App\Actions\Payments;

use App\Actions\Registration\ActivateMembershipOnPaymentConfirmed;
use App\Events\PaymentConfirmed;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * DOMAIN_LOGIC.md §10.1 — marks Paid only after the gateway callback is
 * verified (verification itself happens in the controller via
 * PaymentGatewayContract before this Action is even called). Idempotent: a
 * payment already `paid` is a no-op, so a retried/duplicate webhook never
 * double-activates or double-credits anything (§0, §19).
 */
class ConfirmOnlinePayment
{
    public function __construct(
        private readonly ActivateMembershipOnPaymentConfirmed $activate,
        private readonly ConfirmEmiInstallmentPayment $confirmEmiInstallment,
    ) {}

    /**
     * @param  array<string, mixed>  $gatewayPayload
     */
    public function __invoke(Payment $payment, string $providerReference, array $gatewayPayload): void
    {
        $wasAlreadyPaid = DB::transaction(function () use ($payment, $providerReference, $gatewayPayload) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'paid') {
                return true;
            }

            $locked->update([
                'status' => 'paid',
                'provider_reference' => $providerReference,
                'gateway_payload' => $gatewayPayload,
                'paid_at' => now(),
            ]);

            if ($locked->type === 'registration') {
                ($this->activate)($locked->member()->firstOrFail(), null, $locked);
            } elseif ($locked->type === 'emi_installment') {
                ($this->confirmEmiInstallment)($locked);
            }

            return false;
        });

        if (! $wasAlreadyPaid) {
            event(new PaymentConfirmed($payment->refresh()));
        }
    }
}

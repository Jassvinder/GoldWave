<?php

namespace App\Jobs;

use App\Actions\Payments\ConfirmOnlinePayment;
use App\Models\Payment;
use App\Notifications\PaymentSettlementFailed;
use App\Services\Notifier;
use App\Support\PaymentInProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * T-229 (15-10-2026, user decision) — an online payment the gateway has already verified (signature checked in the
 * request) is confirmed in the background: activation / EMI confirmation and all income in one transaction. Unlike an
 * approval, the money has already been taken, so it is tried 3 times; only then are the company users told to finish
 * it by hand. `ConfirmOnlinePayment` is idempotent, so a webhook and the browser return can both queue it safely.
 */
class ProcessOnlinePaymentConfirmation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [10, 60];

    /** @param  array<string, mixed>  $gatewayPayload */
    public function __construct(
        public readonly Payment $payment,
        public readonly string $providerReference,
        public readonly array $gatewayPayload,
    ) {}

    public function handle(ConfirmOnlinePayment $confirm): void
    {
        $confirm($this->payment, $this->providerReference, $this->gatewayPayload);

        PaymentInProgress::release($this->payment->id);
    }

    public function failed(?Throwable $exception): void
    {
        PaymentInProgress::release($this->payment->id);

        Log::error('Online payment confirmation failed after all attempts', [
            'payment_id' => $this->payment->id,
            'provider_reference' => $this->providerReference,
            'error' => $exception?->getMessage(),
        ]);

        Notifier::toSuperAdmins(new PaymentSettlementFailed(
            $this->payment,
            'Online payment confirmation',
            "The gateway has received the money (ref {$this->providerReference}), but the payment is still pending here. Check the server log and complete it by hand.",
        ));
    }
}

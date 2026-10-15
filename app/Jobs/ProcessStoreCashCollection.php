<?php

namespace App\Jobs;

use App\Actions\Payments\CollectCashPaymentViaStoreWallet;
use App\Models\Payment;
use App\Models\Store;
use App\Models\User;
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
 * T-229 (15-10-2026, user decision) — a store's cash collection (§12.2(a)) settled in the background, like a Super
 * Admin approval (T-228): Store Wallet debit, activation / EMI confirmation and all income in one transaction. On
 * failure nothing is kept — the Store Wallet is not charged and the payment stays pending — and the store admin and
 * the company users are told. One attempt only.
 */
class ProcessStoreCashCollection implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public readonly Payment $payment, public readonly Store $store, public readonly User $operator) {}

    public function handle(CollectCashPaymentViaStoreWallet $collect): void
    {
        try {
            $collect($this->payment, $this->store, $this->operator);
        } finally {
            PaymentInProgress::release($this->payment->id);
        }
    }

    public function failed(?Throwable $exception): void
    {
        PaymentInProgress::release($this->payment->id);

        Log::error('Store cash collection failed and was rolled back', [
            'payment_id' => $this->payment->id,
            'store_id' => $this->store->id,
            'operator_id' => $this->operator->id,
            'error' => $exception?->getMessage(),
        ]);

        $advice = 'The Store Wallet was not charged and the payment is still pending. Try again, or contact the company.';
        Notifier::toUser($this->operator, new PaymentSettlementFailed($this->payment, 'Store cash collection', $advice, '/admin/sales'));
        Notifier::toSuperAdmins(new PaymentSettlementFailed($this->payment, 'Store cash collection', $advice));
    }
}

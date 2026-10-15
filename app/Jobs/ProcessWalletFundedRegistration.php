<?php

namespace App\Jobs;

use App\Actions\Registration\ConfirmWalletFundedRegistration;
use App\Models\Member;
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
 * T-229 (15-10-2026, user decision) — an assisted registration paid from a store's or a member's wallet (§12.2(b))
 * is settled in the background, like a Super Admin approval (T-228): wallet debit, Company Wallet credit, activation
 * and all income in one transaction. On failure nothing is kept — the payer's wallet is not charged and the
 * registration stays pending — and the payer and the company users are told. One attempt only.
 */
class ProcessWalletFundedRegistration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public readonly Member $newMember,
        public readonly Payment $payment,
        public readonly ?Store $payingStore,
        public readonly ?Member $payingMember,
        public readonly User $operator,
    ) {}

    public function handle(ConfirmWalletFundedRegistration $confirm): void
    {
        try {
            $confirm($this->newMember, $this->payingStore, $this->payingMember, $this->operator);
        } finally {
            PaymentInProgress::release($this->payment->id);
        }
    }

    public function failed(?Throwable $exception): void
    {
        PaymentInProgress::release($this->payment->id);

        Log::error('Wallet-funded registration failed and was rolled back', [
            'payment_id' => $this->payment->id,
            'new_member_id' => $this->newMember->id,
            'operator_id' => $this->operator->id,
            'error' => $exception?->getMessage(),
        ]);

        $advice = 'No wallet was charged and the registration is still pending. Contact the company to complete it.';
        Notifier::toUser($this->operator, new PaymentSettlementFailed($this->payment, 'Wallet registration payment', $advice));
        Notifier::toSuperAdmins(new PaymentSettlementFailed($this->payment, 'Wallet registration payment', $advice));
    }
}

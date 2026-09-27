<?php

namespace App\Actions\Payments;

use App\Actions\Registration\ActivateMembershipOnPaymentConfirmed;
use App\Events\PaymentConfirmed;
use App\Models\Payment;
use App\Models\Store;
use App\Models\User;
use App\Notifications\CashPaymentDecided;
use App\Services\Notifier;
use App\Services\StoreWalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §12.2(a) — T-151, Store-Wallet-Funded Cash Collection.
 * A member pays a registration or EMI installment in cash directly to a
 * store; the store settles it instantly from its own Store Wallet instead
 * of Super Admin manually approving it — the store's own money moving is
 * itself the authorization, matching `ConfirmStoreSale`'s existing
 * `payment_source=store_wallet` precedent for `store_sales`. Otherwise
 * identical to `ApproveCashPayment` (same activation/confirmation paths,
 * same event), just with a different settlement mechanism and operator
 * (the Store Admin, not Super Admin) — deliberately reuses the same
 * downstream Actions rather than duplicating them.
 */
class CollectCashPaymentViaStoreWallet
{
    public function __construct(
        private readonly ActivateMembershipOnPaymentConfirmed $activate,
        private readonly ConfirmEmiInstallmentPayment $confirmEmiInstallment,
        private readonly StoreWalletService $storeWallet,
    ) {}

    public function __invoke(Payment $payment, Store $store, User $operator): void
    {
        DB::transaction(function () use ($payment, $store, $operator) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->mode !== 'cash') {
                throw ValidationException::withMessages(['payment' => 'This payment is not a cash payment.']);
            }

            if ($locked->status === 'paid') {
                throw ValidationException::withMessages(['payment' => 'This payment has already been settled.']);
            }

            $wallet = $store->wallet()->firstOrFail();

            $this->storeWallet->deduct(
                $wallet,
                (float) $locked->amount,
                $operator,
                "Store-collected cash — {$locked->type} — Customer {$locked->member?->customer_id}",
            );

            $locked->update([
                'status' => 'paid',
                'cash_status' => 'approved',
                'paying_store_id' => $store->id,
                'verified_by' => $operator->id,
                'verified_at' => now(),
                'paid_at' => now(),
            ]);

            if ($locked->type === 'registration') {
                ($this->activate)($locked->member()->firstOrFail(), $operator->id, $locked);
            } elseif ($locked->type === 'emi_installment') {
                ($this->confirmEmiInstallment)($locked);
            }
        });

        event(new PaymentConfirmed($payment->refresh()));

        Notifier::toUser($payment->member->user, new CashPaymentDecided($payment, true));
    }
}

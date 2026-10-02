<?php

namespace App\Actions\Registration;

use App\Events\PaymentConfirmed;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Store;
use App\Models\User;
use App\Notifications\AssistedRegistrationConfirmed;
use App\Services\CompanyWalletService;
use App\Services\Notifier;
use App\Services\StoreWalletService;
use App\Services\WalletLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §12.2(b) — T-153, Assisted Registration. Settles a
 * `mode=wallet` registration `payments` row instantly: debits the payer's
 * own wallet (a Store via `StoreWalletService`, or a *different* Member via
 * `WalletLedgerService::debit()` — never the new member's own wallet, which
 * does not exist yet), credits the new Company Wallet, activates the new
 * member exactly like `ApproveCashPayment` does for a registration, and
 * fires `PaymentConfirmed` — so Level Income/Pair/Booster etc. evaluate
 * from the *new* member's own Sponsor/Placement chain exactly as normal
 * (§12.2(b): "koi earning distribution me changes nahi honge"). Exactly one
 * of `$payingStore`/`$payingMember` must be given.
 */
class ConfirmWalletFundedRegistration
{
    public function __construct(
        private readonly ActivateMembershipOnPaymentConfirmed $activate,
        private readonly StoreWalletService $storeWallet,
        private readonly WalletLedgerService $memberWallet,
        private readonly CompanyWalletService $companyWallet,
    ) {}

    public function __invoke(Member $newMember, ?Store $payingStore, ?Member $payingMember, User $operator): void
    {
        if (($payingStore === null) === ($payingMember === null)) {
            throw ValidationException::withMessages([
                'payer' => 'Exactly one of a paying store or a paying member is required.',
            ]);
        }

        DB::transaction(function () use ($newMember, $payingStore, $payingMember, $operator) {
            // Filtered by mode in the query itself, not a `$payment->mode === 'wallet'` PHP
            // comparison afterward: `mode`'s CHECK constraint gained `wallet` via a raw ALTER
            // TABLE (Postgres's enum()->change() emits invalid SQL — see the migration's own
            // docblock), which Larastan's static migration scanner never sees, so it still
            // narrows the *model attribute's* type to the original Schema::create migration's
            // literal union ('cash'|'online') and flags any direct PHP comparison against
            // 'wallet' as impossible. The query builder has no such narrowing.
            $payment = Payment::where('member_id', $newMember->id)
                ->where('type', 'registration')
                ->where('mode', 'wallet')
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                throw ValidationException::withMessages(['payment' => 'This registration is not wallet-funded.']);
            }

            if ($payment->status === 'paid') {
                throw ValidationException::withMessages(['payment' => 'This registration has already been settled.']);
            }

            $amount = (float) $payment->amount;
            $description = "Assisted Registration — Customer {$newMember->customer_id}";

            if ($payingStore !== null) {
                $wallet = $payingStore->wallet()->firstOrFail();
                $this->storeWallet->deduct($wallet, $amount, $operator, $description);
                $payment->update(['paying_store_id' => $payingStore->id]);
            } else {
                $this->memberWallet->debit($payingMember, 'assisted_registration', $amount, $payment, $description);
                $payment->update(['paying_member_id' => $payingMember->id]);
            }

            $this->companyWallet->credit('assisted_registration', $amount, $payment, $description);

            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            ($this->activate)($newMember, $operator->id, $payment->fresh());
        });

        $payment = Payment::where('member_id', $newMember->id)->where('type', 'registration')->firstOrFail();

        event(new PaymentConfirmed($payment));

        Notifier::toUser($newMember->fresh()->user, new AssistedRegistrationConfirmed($payment));
    }
}

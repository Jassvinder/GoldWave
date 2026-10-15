<?php

namespace App\Actions\Registration;

use App\Jobs\ProcessWalletFundedRegistration;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Store;
use App\Models\User;
use App\Services\WalletLedgerService;
use App\Support\PaymentInProgress;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * T-229 (15-10-2026) — the request half of a wallet-funded assisted registration (§12.2(b)): registers the new member
 * and queues the settlement (`ProcessWalletFundedRegistration`). Refused, with nothing saved, when the payer's wallet
 * can't cover the registration amount — before, a short wallet left a pending member behind.
 */
class QueueWalletFundedRegistration
{
    public function __construct(
        private readonly RegisterMember $register,
        private readonly WalletLedgerService $memberWallet,
    ) {}

    /** @param  array<string, mixed>  $data  validated AssistedRegisterMemberRequest input */
    public function __invoke(array $data, ?Store $payingStore, ?Member $payingMember, User $operator): Member
    {
        /** @var array{0: Member, 1: Payment} $registered */
        $registered = DB::transaction(function () use ($data, $payingStore, $payingMember) {
            $member = ($this->register)($data);
            $payment = $member->payments->firstWhere('type', 'registration');

            if (! $payment instanceof Payment) {
                throw ValidationException::withMessages(['payment_mode' => 'The registration payment could not be created.']);
            }

            $balance = $payingStore !== null
                ? (float) $payingStore->wallet()->firstOrFail()->balance
                : $this->memberWallet->availableBalance($payingMember?->fresh() ?? throw ValidationException::withMessages(['payment_mode' => 'No wallet to pay from.']));

            if ((float) $payment->amount > $balance) {
                throw ValidationException::withMessages([
                    'payment_mode' => 'Insufficient wallet balance: this registration needs ₹'.number_format((float) $payment->amount, 2).'.',
                ]);
            }

            return [$member, $payment];
        });

        [$member, $payment] = $registered;

        PaymentInProgress::claim($payment->id);
        ProcessWalletFundedRegistration::dispatch($member, $payment, $payingStore, $payingMember, $operator);

        return $member;
    }
}

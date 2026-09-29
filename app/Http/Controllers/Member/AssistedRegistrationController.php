<?php

namespace App\Http\Controllers\Member;

use App\Actions\Registration\ConfirmWalletFundedRegistration;
use App\Actions\Registration\RegisterMember;
use App\Contracts\PaymentGatewayContract;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Registration\RegistrationController;
use App\Http\Requests\Registration\AssistedRegisterMemberRequest;
use App\Models\MembershipPlan;
use App\Services\WalletLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DOMAIN_LOGIC.md §12.2(b) — T-153, Assisted Registration. A logged-in
 * Member registers a *different, new* member — the form is otherwise
 * identical to the public `/join` page; the only addition is a Wallet
 * payment option, funding the new member's registration from this
 * member's own wallet balance instead of the new member paying cash/online
 * themselves. Reuses `RegisterMember` untouched; `payment_mode=wallet`
 * settles immediately via `ConfirmWalletFundedRegistration` right after.
 */
class AssistedRegistrationController extends Controller
{
    public function __construct(private readonly WalletLedgerService $wallet) {}

    public function show(Request $request): Response
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        return Inertia::render('member/assisted-registration', [
            'plans' => MembershipPlan::where('is_active', true)->orderBy('id')->get([
                'id', 'code', 'name', 'amount', 'installment_count', 'product_category', 'fixed_weight_grams',
            ]),
            'wallet_balance' => $this->wallet->availableBalance($member),
            // T-162 — this member's permanent link to the public join page with them as sponsor.
            'referral_link' => route('registration.show', ['ref' => $member->referralCode()]),
        ]);
    }

    public function store(
        AssistedRegisterMemberRequest $request,
        RegisterMember $registerMember,
        ConfirmWalletFundedRegistration $confirmWallet,
        PaymentGatewayContract $gateway,
    ): RedirectResponse {
        $payer = $request->user()->member;

        abort_if($payer === null, 404);

        // Branches on the request's own validated input, not `$payment->mode` re-read off the
        // model — that attribute's Larastan-inferred type still trails the `wallet` value added
        // to the CHECK constraint by raw SQL (see ConfirmWalletFundedRegistration's comment).
        $paymentMode = $request->string('payment_mode')->toString();

        $newMember = $registerMember($request->validated());

        if ($paymentMode === 'wallet') {
            $confirmWallet($newMember, null, $payer, $request->user());

            return redirect()->route('member.assisted-registration.show')
                ->with('status', "Registration confirmed for Customer ID {$newMember->fresh()->customer_id}.");
        }

        $payment = $newMember->payments->firstWhere('type', 'registration');

        if ($paymentMode === 'online') {
            try {
                $intent = $gateway->createIntent($payment);
            } catch (ValidationException $e) {
                return redirect(RegistrationController::signedStatusUrl($newMember))
                    ->with('payment_error', collect($e->errors())->flatten()->first());
            }

            return redirect($intent['redirect_url']);
        }

        // Cash — same signed status page the public /join flow uses, for the new member's own payment/receipt tracking.
        return redirect(RegistrationController::signedStatusUrl($newMember));
    }
}

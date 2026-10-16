<?php

namespace App\Http\Controllers\Member;

use App\Actions\Payout\SubmitPayoutRequest;
use App\Actions\Registration\QueueWalletFundedRegistration;
use App\Actions\Registration\RegisterMember;
use App\Actions\Registration\SendWalletPaymentOtp;
use App\Actions\Registration\VerifyWalletPaymentOtp;
use App\Contracts\PaymentGatewayContract;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Registration\RegistrationController;
use App\Http\Requests\Registration\AssistedRegisterMemberRequest;
use App\Models\MembershipPlan;
use App\Services\Payments\PaymentModes;
use App\Services\WalletLedgerService;
use Illuminate\Http\JsonResponse;
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
 * is settled by `ConfirmWalletFundedRegistration` in the background (T-229, `QueueWalletFundedRegistration`).
 */
class AssistedRegistrationController extends Controller
{
    public function __construct(private readonly WalletLedgerService $wallet) {}

    public function show(Request $request): Response
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        return Inertia::render('member/assisted-registration', [
            'payment_options' => PaymentModes::forPage(withWallet: true),
            'plans' => MembershipPlan::where('is_active', true)->orderBy('id')->get([
                'id', 'code', 'name', 'amount', 'installment_count', 'product_category', 'fixed_weight_grams',
            ]),
            'wallet_balance' => $this->wallet->availableBalance($member),
            // T-162 — this member's permanent link to the public join page with them as sponsor.
            'referral_link' => route('registration.show', ['ref' => $member->referralCode()]),
            // T-215 — the wallet-payment code goes here; a recent email/mobile change holds wallet payments for 48 h.
            'wallet_otp_email' => SendWalletPaymentOtp::maskEmail($request->user()->email),
            // T-231 — a Payout Hold also blocks wallet-paid registrations, shown the same way.
            'wallet_hold_message' => $member->cannotTakeMoneyOut()
                ? SubmitPayoutRequest::ON_HOLD_MESSAGE
                : (($holdUntil = $request->user()->walletPaymentHoldUntil())
                    ? SendWalletPaymentOtp::holdMessage($holdUntil->format('d-m-Y h:i A'))
                    : null),
        ]);
    }

    /** T-215 — sends the code that approves this member's wallet payment (DOMAIN_LOGIC.md §12.2(d)). */
    public function sendWalletOtp(Request $request, SendWalletPaymentOtp $send): JsonResponse
    {
        abort_if($request->user()->member === null, 404);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'membership_plan_id' => ['nullable', 'integer'],
        ]);

        $planName = isset($data['membership_plan_id'])
            ? MembershipPlan::whereKey($data['membership_plan_id'])->value('name')
            : null;

        $sentTo = $send($request->user(), $data['name'] ?? null, $planName);

        return response()->json(['sent_to' => $sentTo]);
    }

    public function store(
        AssistedRegisterMemberRequest $request,
        RegisterMember $registerMember,
        QueueWalletFundedRegistration $queueWalletRegistration,
        VerifyWalletPaymentOtp $verifyWalletOtp,
    ): RedirectResponse {
        $payer = $request->user()->member;

        abort_if($payer === null, 404);

        // Branches on the request's own validated input, not `$payment->mode` re-read off the
        // model — that attribute's Larastan-inferred type still trails the `wallet` value added
        // to the CHECK constraint by raw SQL (see ConfirmWalletFundedRegistration's comment).
        $paymentMode = $request->string('payment_mode')->toString();

        // T-215 — checked before anything is created, so a wrong code leaves no pending member behind.
        if ($paymentMode === 'wallet') {
            $verifyWalletOtp($request->user(), $request->string('wallet_otp')->toString());
        }

        // T-229 — the wallet payment is settled in the background; the status page follows it to "Active".
        if ($paymentMode === 'wallet') {
            $newMember = $queueWalletRegistration($request->validated(), null, $payer, $request->user());

            return redirect(RegistrationController::signedStatusUrl($newMember));
        }

        $newMember = $registerMember($request->validated());

        $payment = $newMember->payments->firstWhere('type', 'registration');
        PaymentModes::attachUpiProof($payment, $request);

        if ($paymentMode === 'online') {
            try {
                $intent = app(PaymentGatewayContract::class)->createIntent($payment);
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

<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Registration\ConfirmWalletFundedRegistration;
use App\Actions\Registration\RegisterMember;
use App\Contracts\PaymentGatewayContract;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Registration\RegistrationController;
use App\Http\Requests\Registration\AssistedRegisterMemberRequest;
use App\Models\MembershipPlan;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DOMAIN_LOGIC.md §12.2(b) — T-153, Assisted Registration. A Store registers
 * a *different, new* member — identical to the Member-portal version
 * (`Member\AssistedRegistrationController`) except the Wallet payment option
 * funds it from this store's own Store Wallet instead of a member's wallet.
 */
class AssistedRegistrationController extends Controller
{
    public function show(Request $request): Response
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        return Inertia::render('admin/assisted-registration', [
            'plans' => MembershipPlan::where('is_active', true)->orderBy('id')->get([
                'id', 'code', 'name', 'amount', 'installment_count', 'product_category', 'fixed_weight_grams',
            ]),
            'wallet_balance' => (float) $store->wallet->fresh()->balance,
        ]);
    }

    public function store(
        AssistedRegisterMemberRequest $request,
        RegisterMember $registerMember,
        ConfirmWalletFundedRegistration $confirmWallet,
        PaymentGatewayContract $gateway,
    ): RedirectResponse {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        // Branches on the request's own validated input, not `$payment->mode` re-read off the
        // model — that attribute's Larastan-inferred type still trails the `wallet` value added
        // to the CHECK constraint by raw SQL (see ConfirmWalletFundedRegistration's comment).
        $paymentMode = $request->string('payment_mode')->toString();

        $newMember = $registerMember($request->validated());

        if ($paymentMode === 'wallet') {
            $confirmWallet($newMember, $store, null, $request->user());

            return redirect()->route('admin.assisted-registration.show')
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

        return redirect(RegistrationController::signedStatusUrl($newMember));
    }
}

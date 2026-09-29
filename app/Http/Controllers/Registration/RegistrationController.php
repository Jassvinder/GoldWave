<?php

namespace App\Http\Controllers\Registration;

use App\Actions\Registration\RegisterMember;
use App\Actions\Registration\ValidateSponsorCode;
use App\Contracts\PaymentGatewayContract;
use App\Http\Controllers\Controller;
use App\Http\Requests\Registration\RegisterMemberRequest;
use App\Http\Requests\Registration\ValidateSponsorCodeRequest;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Services\Payments\RazorpayGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DOMAIN_LOGIC.md §3.1 — the public registration page and its submit
 * endpoint. Business logic lives entirely in the Actions this controller
 * calls (ARCHITECTURE.md's thin-controller rule).
 */
class RegistrationController extends Controller
{
    public function show(Request $request): Response
    {
        // T-162 — a member's registration link (`/join?ref=…`) arrives with its sponsor already chosen.
        // An unknown code is simply ignored: the visitor gets the normal form and types a sponsor code.
        $ref = $request->string('ref')->toString();
        $referrer = $ref !== '' ? Member::with('user')->where('referral_code', $ref)->first() : null;

        return Inertia::render('registration/register', [
            'plans' => MembershipPlan::where('is_active', true)->orderBy('id')->get([
                'id', 'code', 'name', 'amount', 'installment_count', 'product_category', 'fixed_weight_grams',
            ]),
            'referral' => $referrer ? [
                'sponsor_customer_id' => $referrer->customer_id,
                'sponsor_name' => $referrer->user?->name,
            ] : null,
            'referral_invalid' => $ref !== '' && $referrer === null,
        ]);
    }

    public function validateSponsor(ValidateSponsorCodeRequest $request, ValidateSponsorCode $action): JsonResponse
    {
        try {
            $sponsor = $action($request->string('sponsor_code')->toString());
        } catch (ValidationException $e) {
            return response()->json(['valid' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'valid' => true,
            'sponsor_name' => $sponsor->user?->name,
            'sponsor_customer_id' => $sponsor->customer_id,
        ]);
    }

    public function store(RegisterMemberRequest $request, RegisterMember $action, PaymentGatewayContract $gateway): RedirectResponse
    {
        $member = $action($request->validated());

        $payment = $member->payments->firstWhere('type', 'registration');

        abort_if($payment === null, 500, 'Registration payment record was not created.');

        if ($payment->mode === 'online') {
            try {
                $intent = $gateway->createIntent($payment);
            } catch (ValidationException $e) {
                // The registration is already saved; the status page offers "Pay now" so the member can retry.
                return redirect(self::signedStatusUrl($member))
                    ->with('payment_error', collect($e->errors())->flatten()->first());
            }

            return redirect($intent['redirect_url']);
        }

        return redirect(self::signedStatusUrl($member));
    }

    /**
     * The member cannot log in yet (pre-activation, DOMAIN_LOGIC.md §2.2), so
     * this status page can't be auth-gated — a signed URL prevents Customer
     * ID/payment-status enumeration by guessing sequential member IDs.
     */
    public static function signedStatusUrl(Member $member): string
    {
        return URL::temporarySignedRoute('registration.status', now()->addDay(), ['member' => $member->id]);
    }

    public function status(Member $member): Response
    {
        $member->load(['payments', 'emiSchedule', 'membershipPlan']);

        return Inertia::render('registration/status', [
            'member' => [
                'id' => $member->id,
                'customer_id' => $member->customer_id,
                'status' => $member->status,
                'plan' => $member->membershipPlan?->name,
            ],
            'payment' => $payment = $member->payments->firstWhere('type', 'registration'),
            // A still-pending online payment can always be (re)started from here through a fresh signed checkout link.
            'pay_url' => $payment !== null && $payment->mode === 'online' && $payment->status === 'pending'
                ? RazorpayGateway::checkoutUrl($payment)
                : null,
            'payment_error' => session('payment_error'),
        ]);
    }
}

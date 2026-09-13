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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
    public function show(): Response
    {
        return Inertia::render('registration/register', [
            'plans' => MembershipPlan::where('is_active', true)->orderBy('id')->get([
                'id', 'code', 'name', 'amount', 'installment_count', 'product_category', 'fixed_weight_grams',
            ]),
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
            $intent = $gateway->createIntent($payment);

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
            'payment' => $member->payments->firstWhere('type', 'registration'),
        ]);
    }
}

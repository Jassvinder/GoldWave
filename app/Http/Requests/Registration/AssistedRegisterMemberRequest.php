<?php

namespace App\Http\Requests\Registration;

use App\Services\Payments\PaymentModes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * DOMAIN_LOGIC.md §12.2(b) — T-153, Assisted Registration. Identical to the
 * public `RegisterMemberRequest` except `payment_mode` also accepts
 * `wallet` — funding the new member's registration from the *logged-in*
 * Member's or Store's own wallet, never the new member's own (they don't
 * have one yet). The payer's identity always comes from the authenticated
 * session, never a request field, so it can't be spoofed to someone else's
 * wallet.
 */
class AssistedRegisterMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sponsor_code' => ['required', 'string', 'max:20'],
            'placement_side' => ['required', 'in:left,right'],
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female,other'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'mobile' => ['required', 'digits:10', 'unique:users,mobile'],
            'membership_plan_id' => ['required', 'integer', 'exists:membership_plans,id'],
            'payment_mode' => ['required', Rule::in(PaymentModes::offered(withWallet: true))],
            ...PaymentModes::upiProofRules('payment_mode'),
        ];
    }
}

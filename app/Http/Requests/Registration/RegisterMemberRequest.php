<?php

namespace App\Http\Requests\Registration;

use App\Services\Payments\PaymentModes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * DOMAIN_LOGIC.md §2.1/§3.1: mobile and email are both mandatory (§2.1 point
 * 4). There is deliberately no rate-booking field (T-116, 20-09-2026): every
 * EMI plan registers on Future Rate and the member may book at the Current
 * Rate later from the Membership Plan page (§3.0).
 */
class RegisterMemberRequest extends FormRequest
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
            'payment_mode' => ['required', Rule::in(PaymentModes::offered())],
            ...PaymentModes::upiProofRules('payment_mode'),
        ];
    }
}

<?php

namespace App\Http\Requests\Registration;

use App\Models\MembershipPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * DOMAIN_LOGIC.md §2.1/§3.1: mobile and email are both mandatory (§2.1 point
 * 4); rate_booking_method is mandatory for EMI plans A-D only (§3.0) — Plans
 * E/F have no rate-booking step at all, enforced via withValidator since it
 * depends on which plan was selected.
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
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'mobile' => ['required', 'digits:10', 'unique:users,mobile'],
            'membership_plan_id' => ['required', 'integer', 'exists:membership_plans,id'],
            'rate_booking_method' => ['nullable', 'in:current_rate,future_rate'],
            'payment_mode' => ['required', 'in:online,cash'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $plan = MembershipPlan::find((int) $this->input('membership_plan_id'));

            if ($plan && $plan->isEmiPlan() && ! $this->input('rate_booking_method')) {
                $validator->errors()->add('rate_booking_method', 'Select Current Rate Booking or Future Rate Booking for this plan.');
            }
        });
    }
}

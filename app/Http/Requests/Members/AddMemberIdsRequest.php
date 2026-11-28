<?php

namespace App\Http\Requests\Members;

use App\Services\Payments\PaymentModes;
use App\Support\MemberIds;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * DOMAIN_LOGIC.md §3.4 (T-251) — "Add ID" for an existing person, by themselves (My IDs) or by company staff (Member
 * Detail): how many, the plan, where the first new ID goes (any active member's Customer ID + side), and the Cash or
 * GPay/UPI payment for all of them. The limit against the person's existing IDs is checked by `RegisterMemberIds`.
 */
class AddMemberIdsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id_count' => ['required', 'integer', 'min:1', 'max:'.MemberIds::limit()],
            'membership_plan_id' => ['required', 'integer', Rule::exists('membership_plans', 'id')->where('is_active', true)],
            'parent_code' => ['required', 'string', 'max:20'],
            'placement_side' => ['required', 'in:left,right'],
            'payment_mode' => ['required', Rule::in(PaymentModes::NEEDS_APPROVAL)],
            ...PaymentModes::upiProofRules('payment_mode'),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['id_count' => 'number of IDs', 'parent_code' => 'parent Customer ID', 'membership_plan_id' => 'plan'];
    }
}

<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\Member;
use App\Models\MemberRestriction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * T-231 (DOMAIN_LOGIC.md §2.3) — applying or lifting a Payout Hold / Account Block is deliberate: a reason of at least
 * 10 characters, the member's Customer ID typed again, and the Super Admin's own password.
 */
class ChangeMemberRestrictionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'member_id' => ['required', 'integer', Rule::exists('members', 'id')],
            'type' => ['required', Rule::in([MemberRestriction::PAYOUT_HOLD, MemberRestriction::ACCOUNT_BLOCK])],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'confirm_customer_id' => ['required', 'string'],
            'password' => ['required', 'string', 'current_password'],
            'also_lift_payout_hold' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.min' => 'Write the reason in at least 10 characters.',
            'password.current_password' => 'The password is incorrect.',
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $customerId = Member::whereKey($this->integer('member_id'))->value('customer_id');

                if ($customerId === null || strtoupper(trim($this->string('confirm_customer_id')->toString())) !== strtoupper($customerId)) {
                    $validator->errors()->add('confirm_customer_id', 'Type the member\'s Customer ID exactly to confirm.');
                }
            },
        ];
    }

    public function member(): Member
    {
        return Member::findOrFail($this->integer('member_id'));
    }
}

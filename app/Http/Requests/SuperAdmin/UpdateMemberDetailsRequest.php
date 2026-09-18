<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * T-106 (17-09-2026) — Super Admin's direct Member edit form. Per the user's
 * explicit instruction, every field except `customer_id` is editable here,
 * bypassing the member-initiated Change Request/approval workflow entirely
 * (that workflow, and its audit trail, remains for member self-service
 * edits — this is a separate, Super-Admin-only correction channel). PAN/
 * Aadhaar are optional here (unlike the member's own one-time Pending
 * Fields form) since a Super Admin may be correcting only one field.
 */
class UpdateMemberDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $member = $this->route('member');
        $userId = $member instanceof Member ? $member->user_id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'mobile' => ['nullable', 'string', 'max:20'],
            'pan_card' => ['nullable', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'aadhaar_card' => ['nullable', 'string', 'regex:/^[0-9]{12}$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'profile_photo' => ['nullable', 'image', 'max:5120'],
            'bank_account_holder_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:34'],
            'bank_ifsc_code' => ['nullable', 'string', 'max:11'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_proof_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}

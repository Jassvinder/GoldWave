<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DOMAIN_LOGIC.md §13 — the one-time Pending Fields form (M03). PAN/Aadhaar
 * format is re-validated here for a friendly inline error, but
 * `SubmitPendingProfileFields` is the actual source of truth (never trust
 * client-side validation alone for a business rule).
 */
class SubmitPendingProfileFieldsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'pan_card' => ['required', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'aadhaar_card' => ['required', 'string', 'regex:/^[0-9]{12}$/'],
            'profile_photo' => ['required', 'image', 'max:5120'],
            'address' => ['required', 'string', 'max:1000'],
            'bank_account_holder_name' => ['required', 'string', 'max:255'],
            'bank_account_number' => ['required', 'string', 'max:34'],
            'bank_ifsc_code' => ['required', 'string', 'max:11'],
            'bank_name' => ['required', 'string', 'max:255'],
            'bank_proof_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}

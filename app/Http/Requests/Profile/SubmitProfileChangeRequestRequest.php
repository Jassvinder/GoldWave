<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * DOMAIN_LOGIC.md §13 point 3 (M04) — a locked field's correction request.
 * `bank_details` submits its 4 sub-fields instead of a single `new_value`.
 */
class SubmitProfileChangeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'field_name' => ['required', 'in:pan_card,aadhaar_card,address,profile_photo_path,bank_details'],
            'reason' => ['nullable', 'string', 'max:500'],
            'new_value' => ['required_if:field_name,pan_card,aadhaar_card,address', 'nullable', 'string', 'max:1000'],
            'new_photo' => ['required_if:field_name,profile_photo_path', 'nullable', 'image', 'max:5120'],
            'bank_account_holder_name' => ['required_if:field_name,bank_details', 'nullable', 'string', 'max:255'],
            'bank_account_number' => ['required_if:field_name,bank_details', 'nullable', 'string', 'max:34'],
            'bank_ifsc_code' => ['required_if:field_name,bank_details', 'nullable', 'string', 'max:11'],
            'bank_name' => ['required_if:field_name,bank_details', 'nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('field_name') === 'pan_card' && $this->filled('new_value')
                && ! preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', (string) $this->input('new_value'))) {
                $validator->errors()->add('new_value', 'Invalid PAN format.');
            }

            if ($this->input('field_name') === 'aadhaar_card' && $this->filled('new_value')
                && ! preg_match('/^[0-9]{12}$/', (string) $this->input('new_value'))) {
                $validator->errors()->add('new_value', 'Invalid Aadhaar format.');
            }
        });
    }
}

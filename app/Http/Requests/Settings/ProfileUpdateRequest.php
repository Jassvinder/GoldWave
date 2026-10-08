<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = $this->profileRules($this->user()->id);

        // DOMAIN_LOGIC.md §12.2(d) (T-215): only Super Admin / company Admin may change their own email here; a
        // Member's or Store Admin's email is changed only by the office, from Member Management.
        // Sending the unchanged email is harmless; a different one is refused.
        if (! $this->user()->isCompanyStaff()) {
            $rules['email'] = ['sometimes', Rule::in([$this->user()->email])];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.in' => 'To change your email or mobile number, please visit the GoldWave office.',
        ];
    }
}

<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetStorePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'password_mode' => ['required', 'in:auto,manual'],
            'password' => ['required_if:password_mode,manual', 'nullable', Password::defaults()],
        ];
    }
}

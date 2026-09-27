<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * T-117 (19-09-2026) — `password_mode`/`password` only matter when an owner
 * is set at creation time (a store with no owner has nothing to log in
 * as); `store_code` is not user-supplied, it's always system-generated.
 */
class CreateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'contact' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'jewellery_allocation_value' => ['required', 'numeric', 'min:0'],
            'advance_amount' => ['required', 'numeric', 'min:0'],
            'password_mode' => ['required_with:owner_user_id', 'nullable', 'in:auto,manual'],
            'password' => ['required_if:password_mode,manual', 'nullable', Password::defaults()],
        ];
    }
}

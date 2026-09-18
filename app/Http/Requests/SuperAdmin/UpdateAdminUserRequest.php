<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $adminUser = $this->route('admin_user');
        $userId = $adminUser instanceof User ? $adminUser->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'mobile' => ['nullable', 'string', 'max:20'],
        ];
    }
}

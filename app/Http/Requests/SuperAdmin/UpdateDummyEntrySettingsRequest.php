<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDummyEntrySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'daily_count' => ['required', 'integer', 'min:0'],
            // T-237 — any active EMI plan, including ones added on Membership Plans (DOMAIN_LOGIC.md §3.3).
            'plan_code' => ['required', 'string', Rule::exists('membership_plans', 'code')->where('is_active', true)->whereNotNull('installment_count')],
        ];
    }
}

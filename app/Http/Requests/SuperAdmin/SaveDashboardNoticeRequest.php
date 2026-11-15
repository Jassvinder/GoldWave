<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\DashboardNotice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** T-245 — one offer/event/notice for the member dashboard. Both dates are optional; the end can't be before the start. */
class SaveDashboardNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(DashboardNotice::KINDS)],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:1000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['ends_on.after_or_equal' => 'The end date cannot be before the start date.'];
    }
}

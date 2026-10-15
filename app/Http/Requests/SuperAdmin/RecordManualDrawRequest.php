<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

/** T-221 — the Customer IDs drawn in person for a group's next month; `ExecuteMonthlyDraw::recordManual()` checks them. */
class RecordManualDrawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_ids' => ['required', 'array', 'min:1', 'max:50'],
            'customer_ids.*' => ['nullable', 'string', 'max:50'],
        ];
    }

    /** @return list<string> */
    public function customerIds(): array
    {
        return array_values(array_map(fn (mixed $id): string => (string) $id, $this->input('customer_ids', [])));
    }
}

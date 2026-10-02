<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class SetMetalRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'metal' => ['required', 'in:gold,silver'],
            // T-165 — entered per 10 gm; at most 1 decimal so the stored per-gram rate stays exact.
            'rate_per_10_grams' => ['required', 'numeric', 'decimal:0,1', 'min:0.1'],
            'making_charge_percent' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'effective_from' => ['required', 'date'],
        ];
    }
}

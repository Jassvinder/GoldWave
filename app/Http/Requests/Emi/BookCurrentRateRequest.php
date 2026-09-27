<?php

namespace App\Http\Requests\Emi;

use Illuminate\Foundation\Http\FormRequest;

/** DOMAIN_LOGIC.md §3.0 (T-116) — the rate and paid-EMI count the member saw in the popup, so a stale quote can be refused. */
class BookCurrentRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'metal_rate_id' => ['required', 'integer'],
            'paid_installments' => ['required', 'integer', 'min:0'],
        ];
    }
}

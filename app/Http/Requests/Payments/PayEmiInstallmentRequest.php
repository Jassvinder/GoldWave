<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

/** DOMAIN_LOGIC.md §10 — same Online/Cash choice as registration payment (§3.1). */
class PayEmiInstallmentRequest extends FormRequest
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
            'mode' => ['required', 'in:online,cash'],
        ];
    }
}

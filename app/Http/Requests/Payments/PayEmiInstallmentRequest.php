<?php

namespace App\Http\Requests\Payments;

use App\Services\Payments\PaymentModes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** DOMAIN_LOGIC.md §10 — same payment choice as registration (§3.1): Cash / GPay-UPI (+ Online when enabled), T-196. */
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
            'mode' => ['required', Rule::in(PaymentModes::offered())],
            ...PaymentModes::upiProofRules('mode'),
        ];
    }
}

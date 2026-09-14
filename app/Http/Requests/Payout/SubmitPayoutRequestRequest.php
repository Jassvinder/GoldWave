<?php

namespace App\Http\Requests\Payout;

use Illuminate\Foundation\Http\FormRequest;

/** DOMAIN_LOGIC.md §11.1 (M15) — `SubmitPayoutRequest` re-validates the minimum/balance itself; this is just the request shape. */
class SubmitPayoutRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
        ];
    }
}

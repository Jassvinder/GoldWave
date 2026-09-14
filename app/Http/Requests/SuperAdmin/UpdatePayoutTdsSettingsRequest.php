<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayoutTdsSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'payout_min_amount' => ['required', 'numeric', 'min:0'],
            'payout_tds_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'payout_processing_fee_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}

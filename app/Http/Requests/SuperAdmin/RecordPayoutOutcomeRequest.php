<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

/** T-109 (17-09-2026) — shared by both `PayoutRequestController::process()` and `::fail()`; the two Actions take identical inputs. */
class RecordPayoutOutcomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'method' => ['required', 'in:cheque,gpay_upi,bank_transfer,in_app_provider'],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}

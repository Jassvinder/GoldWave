<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

/** DOMAIN_LOGIC.md §3.0 — a Super Admin's revert of a member's Current Rate booking always carries a reason (it goes to the audit log). */
class RevertCurrentRateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}

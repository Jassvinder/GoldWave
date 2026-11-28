<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * T-252 (DOMAIN_LOGIC.md §3.5) — the new sponsor (or, for an "Add ID" ID, the parent) Customer ID, the side, and why.
 * Whether the member can still be moved is checked by `ChangePendingPlacement`.
 */
class ChangePendingPlacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
            'placement_side' => ['required', 'in:left,right'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['code' => 'Customer ID', 'placement_side' => 'side'];
    }
}

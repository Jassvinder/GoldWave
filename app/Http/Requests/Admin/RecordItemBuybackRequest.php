<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * INSTRUCTIONS.md A03 — Item Buyback (DOMAIN_LOGIC.md §16.7). A seller is
 * primarily a non-member walk-in (revised 23-09-2026, user decision) — give
 * either `customer_id` (a member) or `walk_in_name` (a non-member), not
 * both; `walk_in_mobile` is optional either way.
 */
class RecordItemBuybackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'string', 'max:20'],
            'walk_in_name' => ['required_without:customer_id', 'nullable', 'string', 'max:255'],
            'walk_in_mobile' => ['nullable', 'string', 'max:20'],
            'item_name' => ['required', 'string', 'max:255'],
            'metal' => ['required', 'in:gold,silver'],
            'weight' => ['required', 'numeric', 'min:0.001'],
            'quantity' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

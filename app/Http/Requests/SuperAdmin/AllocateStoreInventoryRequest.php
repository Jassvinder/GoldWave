<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

/** DOMAIN_LOGIC.md §16.5 — every inventory item record: name, metal, weight, quantity, price, an optional short description. */
class AllocateStoreInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'item_name' => ['required', 'string', 'max:255'],
            'metal' => ['required', 'in:gold,silver'],
            'weight' => ['required', 'numeric', 'min:0.001'],
            'quantity' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}

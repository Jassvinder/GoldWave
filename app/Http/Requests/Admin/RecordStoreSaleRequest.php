<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** INSTRUCTIONS.md A03 — record a New Sale / Purchase / Repurchase (DOMAIN_LOGIC.md §16.2). */
class RecordStoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'transaction_type' => ['required', 'in:new_sale,purchase,repurchase'],
            'customer_id' => ['nullable', 'string', 'max:20'],
            'store_inventory_item_id' => ['nullable', 'integer', 'exists:store_inventory_items,id'],
            'item_name' => ['required_without:store_inventory_item_id', 'nullable', 'string', 'max:255'],
            'item_weight' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:1'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'sale_amount' => ['required', 'numeric', 'min:0'],
            'gst_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_source' => ['required', 'in:cash,store_wallet,other'],
        ];
    }
}

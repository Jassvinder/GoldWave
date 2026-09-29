<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** INSTRUCTIONS.md A03 — plan jewellery delivery for a new joining (DOMAIN_LOGIC.md §16.10). */
class RecordPlanJewelleryDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'string', 'max:20'],
            // T-168 — the delivered piece comes from this store's stock; the price is worked out by the server.
            'store_inventory_item_id' => ['required', 'integer', 'exists:store_inventory_items,id'],
        ];
    }
}

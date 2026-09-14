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
            'sale_amount' => ['required', 'numeric', 'min:0'],
            'gst_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

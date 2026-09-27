<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * INSTRUCTIONS.md A03 — record a Purchase / Repurchase (DOMAIN_LOGIC.md
 * §16.2). **Revised 23-09-2026 (user decision):** `new_sale` is no longer a
 * manual-entry option here — it is reserved for the automatic registration/
 * plan-jewellery-delivery path (`RecordPlanJewelleryDelivery`, §16.10),
 * which calls `ConfirmStoreSale` directly and never goes through this
 * Request. A Purchase covers both a member's own purchase and a non-member
 * walk-in's (distinguished only by whether `customer_id` is given — no
 * separate flag needed); a Repurchase always requires a member.
 */
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
            'transaction_type' => ['required', 'in:purchase,repurchase'],
            'customer_id' => ['required_if:transaction_type,repurchase', 'nullable', 'string', 'max:20'],
            'store_inventory_item_id' => ['nullable', 'integer', 'exists:store_inventory_items,id'],
            'item_name' => ['required_without:store_inventory_item_id', 'nullable', 'string', 'max:255'],
            // T-110 (19-09-2026) — a tracked inventory item already knows its
            // own metal; a manual item entry has no other way to record
            // which Gold/Silver rate table this sale's compensation should use.
            'metal' => ['required_without:store_inventory_item_id', 'nullable', 'in:gold,silver'],
            'item_weight' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:1'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'sale_amount' => ['required', 'numeric', 'min:0'],
            'gst_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_source' => ['required', 'in:cash,store_wallet,other'],
        ];
    }
}

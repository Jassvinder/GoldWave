<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDrawSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'draw_group_size' => ['required', 'integer', 'min:1'],
            'draw_prize_silver_name' => ['required', 'string', 'max:255'],
            'draw_prize_silver_value' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'draw_prize_gold_name' => ['required', 'string', 'max:255'],
            'draw_prize_gold_value' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'draw_winners_per_month' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }
}

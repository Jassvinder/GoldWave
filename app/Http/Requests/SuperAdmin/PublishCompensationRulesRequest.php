<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * INSTRUCTIONS.md S03 / Admin Compensation Management's config page. Every
 * field is optional — only the keys actually submitted are overridden, the
 * rest carry forward unchanged (`PublishRuleVersion`'s design, DOMAIN_LOGIC.md
 * §21 T-017 pre-coding pass).
 */
class PublishCompensationRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
            'level_income_rates' => ['nullable', 'array'],
            'level_income_rates.*' => ['numeric', 'min:0'],
            'pair_value_per_entry' => ['nullable', 'numeric', 'min:0'],
            'pair_milestones' => ['nullable', 'array'],
            'pair_milestones.*.milestone_no' => ['required_with:pair_milestones', 'integer', 'min:1'],
            'pair_milestones.*.min_directs' => ['required_with:pair_milestones', 'integer', 'min:0'],
            'pair_milestones.*.left' => ['required_with:pair_milestones', 'integer', 'min:0'],
            'pair_milestones.*.right' => ['required_with:pair_milestones', 'integer', 'min:0'],
            'pair_qualification_emis' => ['nullable', 'array'],
            'pair_qualification_emis.*' => ['integer', 'min:0'],
            'booster_levels' => ['nullable', 'array'],
            'booster_levels.*.level_no' => ['required_with:booster_levels', 'integer', 'min:1'],
            'booster_levels.*.min_directs' => ['required_with:booster_levels', 'integer', 'min:0'],
            'booster_levels.*.team_split_left' => ['required_with:booster_levels', 'integer', 'min:0'],
            'booster_levels.*.team_split_right' => ['required_with:booster_levels', 'integer', 'min:0'],
            'booster_levels.*.monthly_benefit' => ['required_with:booster_levels', 'numeric', 'min:0'],
            'booster_levels.*.duration_months' => ['required_with:booster_levels', 'integer', 'min:1'],
            'purchase_repurchase_income_rates' => ['nullable', 'array'],
            'purchase_repurchase_income_rates.*' => ['numeric', 'min:0'],
            'store_profit_distribution_rates' => ['nullable', 'array'],
            'store_profit_distribution_rates.*' => ['numeric', 'min:0'],
            'item_buyback_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'store_gst_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}

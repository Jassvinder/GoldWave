<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\MembershipPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * DOMAIN_LOGIC.md §3.3 (T-237) — add or edit a membership plan. Metal and EMI/Direct are asked only when adding;
 * on an edit they are fixed and not accepted. EMI plans also need EMI count, booking weight and Pair/Draw EMIs.
 */
class SaveMembershipPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $plan = $this->plan();
        $required = $this->isEmi() ? 'required' : 'prohibited';

        return [
            'name' => ['required', 'string', 'max:60', Rule::unique('membership_plans', 'name')->ignore($plan?->id)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'is_active' => ['required', 'boolean'],
            'product_category' => $plan ? ['prohibited'] : ['required', 'in:silver,gold'],
            'plan_type' => $plan ? ['prohibited'] : ['required', 'in:emi,direct'],
            'installment_count' => [$required, 'integer', 'min:1', 'max:120'],
            'fixed_weight_grams' => [$required, 'numeric', 'gt:0', 'max:100000'],
            'pair_emis' => [$required, 'integer', 'min:0', 'lte:'.$this->installmentCountFor()],
            'draw_emis' => [$required, 'integer', 'min:0', 'lte:'.$this->installmentCountFor()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'pair_emis.lte' => 'EMIs needed for Pair cannot be more than the number of EMIs.',
            'draw_emis.lte' => 'EMIs needed for Draw cannot be more than the number of EMIs.',
            'name.unique' => 'Another plan already has this name.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'installment_count' => 'number of EMIs',
            'fixed_weight_grams' => 'Current Rate Booking weight',
            'pair_emis' => 'EMIs needed for Pair',
            'draw_emis' => 'EMIs needed for Draw',
            'product_category' => 'metal',
            'plan_type' => 'plan type',
        ];
    }

    public function plan(): ?MembershipPlan
    {
        $plan = $this->route('plan');

        return $plan instanceof MembershipPlan ? $plan : null;
    }

    public function isEmi(): bool
    {
        $plan = $this->plan();

        return $plan ? $plan->isEmiPlan() : $this->input('plan_type') === 'emi';
    }

    private function installmentCountFor(): int
    {
        return max(0, (int) $this->input('installment_count'));
    }
}

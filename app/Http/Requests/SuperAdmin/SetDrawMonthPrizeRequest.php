<?php

namespace App\Http\Requests\SuperAdmin;

use App\Actions\Draw\ExecuteMonthlyDraw;
use App\Models\DrawGroup;
use Illuminate\Foundation\Http\FormRequest;

/** A group's own prize for one draw month (§8.3, 30-09-2026) — only for a month that hasn't been drawn yet. */
class SetDrawMonthPrizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var DrawGroup $group */
        $group = $this->route('group');
        $firstOpenMonth = ((int) $group->executions()->max('cycle_month_no')) + 1;

        return [
            'cycle_month_no' => ['required', 'integer', "min:{$firstOpenMonth}", 'max:'.ExecuteMonthlyDraw::CYCLE_MONTHS],
            'prize_name' => ['required', 'string', 'max:255'],
            'prize_value' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'winners_count' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cycle_month_no.min' => 'That month has already been drawn — its prize can no longer change.',
        ];
    }
}

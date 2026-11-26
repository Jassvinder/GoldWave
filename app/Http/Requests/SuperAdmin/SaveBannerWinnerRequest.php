<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\BannerWinner;
use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * T-248 — one banner winner: group no., draw month (YYYY-MM), Customer ID and prize. The Customer ID must belong to a
 * member (case and spaces don't matter); the name is taken from that member. The same member can't be listed twice for
 * the same month.
 */
class SaveBannerWinnerRequest extends FormRequest
{
    private ?Member $resolvedMember = null;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'group_no' => ['required', 'integer', 'min:1', 'max:9999'],
            'draw_month' => ['required', 'date_format:Y-m'],
            'customer_id' => ['required', 'string', 'max:50'],
            'prize' => ['required', 'string', 'max:120'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $customerId = strtoupper((string) preg_replace('/\s+/', '', $this->string('customer_id')->toString()));
                $member = Member::whereRaw('upper(customer_id) = ?', [$customerId])->first();

                if ($member === null) {
                    $validator->errors()->add('customer_id', "No member has Customer ID {$customerId}.");

                    return;
                }

                /** @var BannerWinner|null $current */
                $current = $this->route('winner');
                $duplicate = BannerWinner::where('member_id', $member->id)
                    ->whereDate('draw_month', $this->drawMonth())
                    ->when($current !== null, fn ($query) => $query->whereKeyNot($current->id))
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add('customer_id', "{$member->customer_id} is already listed for this month.");

                    return;
                }

                $this->resolvedMember = $member;
            },
        ];
    }

    /** The first day of the chosen month (Y-m-d). */
    public function drawMonth(): string
    {
        return Carbon::createFromFormat('!Y-m', $this->string('draw_month')->toString())->toDateString();
    }

    /** @return array{group_no: int, draw_month: string, member_id: int, prize: string} */
    public function winnerData(): array
    {
        return [
            'group_no' => $this->integer('group_no'),
            'draw_month' => $this->drawMonth(),
            'member_id' => (int) $this->resolvedMember?->id,
            'prize' => trim($this->string('prize')->toString()),
        ];
    }
}

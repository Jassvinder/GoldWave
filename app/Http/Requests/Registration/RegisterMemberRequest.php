<?php

namespace App\Http\Requests\Registration;

use App\Services\Payments\PaymentModes;
use App\Support\MemberIds;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * DOMAIN_LOGIC.md §2.1/§3.1: mobile and email are both mandatory (§2.1 point
 * 4). There is deliberately no rate-booking field (T-116, 20-09-2026): every
 * EMI plan registers on Future Rate and the member may book at the Current
 * Rate later from the Membership Plan page (§3.0).
 */
class RegisterMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sponsor_code' => ['required', 'string', 'max:20'],
            'placement_side' => ['required', 'in:left,right'],
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female,other'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'mobile' => ['required', 'digits:10', 'unique:users,mobile'],
            // T-237 — a plan switched Off on Membership Plans cannot be joined (DOMAIN_LOGIC.md §3.3).
            'membership_plan_id' => ['required', 'integer', Rule::exists('membership_plans', 'id')->where('is_active', true)],
            // T-251 — several IDs are paid together by Cash or GPay/UPI only (DOMAIN_LOGIC.md §3.4).
            'payment_mode' => ['required', Rule::in($this->severalIds() ? PaymentModes::NEEDS_APPROVAL : PaymentModes::offered())],
            ...PaymentModes::upiProofRules('payment_mode'),
            'id_count' => self::idCountRules(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return self::idCountMessages($this->severalIds());
    }

    /** T-251 — "Number of IDs" on a registration (DOMAIN_LOGIC.md §3.4); 1 when left out. */
    public function severalIds(): bool
    {
        return (int) $this->input('id_count', 1) > 1;
    }

    /** @return list<mixed> */
    public static function idCountRules(): array
    {
        return ['nullable', 'integer', 'min:1', 'max:'.MemberIds::limit()];
    }

    /** @return array<string, string> */
    public static function idCountMessages(bool $severalIds): array
    {
        return [
            'id_count.max' => 'At most '.MemberIds::limit().' IDs per person.',
            ...($severalIds ? ['payment_mode.in' => 'Several IDs are paid together by Cash or GPay/UPI only.'] : []),
        ];
    }
}

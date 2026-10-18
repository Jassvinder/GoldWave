<?php

namespace App\Actions\Settings;

use App\Models\MembershipPlan;
use App\Models\MembershipPlanChange;
use App\Models\User;
use App\Services\RuleVersionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §3.3 (T-237) — creates or edits a membership plan from the Super Admin Membership Plans page.
 *
 * - The code (A, B, …), EMI vs Direct and the metal are fixed once a plan exists: Pair/Draw rules are keyed to the code
 *   and member schedules depend on the type and metal.
 * - An EMI plan's "EMIs needed for Pair / Draw" live in the rule version (`pair_qualification_emis`,
 *   `draw_eligibility_emis`, keyed by code); a change publishes a new version through `PublishRuleVersion`. A new EMI
 *   plan must have both, or its members would silently never qualify.
 * - Existing members are not touched: their money values are kept on their own payments and EMI schedule.
 * - Every create/edit is logged to `membership_plan_changes`.
 */
class SaveMembershipPlan
{
    public function __construct(
        private readonly RuleVersionService $rules,
        private readonly PublishRuleVersion $publishRuleVersion,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated by `SaveMembershipPlanRequest`: name, amount, is_active; when creating
     *                                      also product_category and plan_type; for EMI plans also installment_count,
     *                                      fixed_weight_grams, pair_emis and draw_emis.
     */
    public function __invoke(?MembershipPlan $plan, array $data, User $operator): MembershipPlan
    {
        return DB::transaction(function () use ($plan, $data, $operator) {
            $isNew = $plan === null;
            $isEmi = $isNew ? ($data['plan_type'] ?? null) === 'emi' : $plan->isEmiPlan();
            $before = $isNew ? null : $this->snapshot($plan);

            if (! $isNew && ! $data['is_active'] && $plan->is_active && $plan->code === $this->dummyPlanCode()) {
                throw ValidationException::withMessages([
                    'is_active' => 'Daily Dummy Entries use this plan. Choose another plan on Dummy Entry Settings before switching it off.',
                ]);
            }

            $plan ??= new MembershipPlan([
                'code' => $this->nextCode(),
                'product_category' => $data['product_category'] ?? null,
            ]);

            $plan->fill([
                'name' => $data['name'],
                'amount' => $data['amount'],
                'installment_count' => $isEmi ? ($data['installment_count'] ?? null) : null,
                'fixed_weight_grams' => $isEmi ? ($data['fixed_weight_grams'] ?? null) : null,
                'is_active' => (bool) $data['is_active'],
            ])->save();

            if ($isEmi) {
                $this->publishThresholds($plan, (int) ($data['pair_emis'] ?? 0), (int) ($data['draw_emis'] ?? 0), $operator);
            }

            MembershipPlanChange::create([
                'membership_plan_id' => $plan->id,
                'changed_by' => $operator->id,
                'action' => $isNew ? 'created' : 'updated',
                'before' => $before,
                'after' => $this->snapshot($plan),
            ]);

            return $plan;
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(MembershipPlan $plan): array
    {
        return [
            'name' => $plan->name,
            'amount' => (float) $plan->amount,
            // Cast: a freshly filled value is the request's string ("10"), which would otherwise log "10 → 10" as a change.
            'installment_count' => $plan->installment_count === null ? null : (int) $plan->installment_count,
            'fixed_weight_grams' => $plan->fixed_weight_grams === null ? null : (float) $plan->fixed_weight_grams,
            'is_active' => (bool) $plan->is_active,
            'pair_emis' => $this->threshold('pair_qualification_emis', $plan->code),
            'draw_emis' => $this->threshold('draw_eligibility_emis', $plan->code),
        ];
    }

    private function threshold(string $key, string $code): ?int
    {
        $value = ((array) $this->rules->value($key, []))[$code] ?? null;

        return $value === null ? null : (int) $value;
    }

    /** Publishes a new rule version only when a number actually changes. */
    private function publishThresholds(MembershipPlan $plan, int $pairEmis, int $drawEmis, User $operator): void
    {
        $pair = (array) $this->rules->value('pair_qualification_emis', []);
        $draw = (array) $this->rules->value('draw_eligibility_emis', []);

        if ($this->threshold('pair_qualification_emis', $plan->code) === $pairEmis
            && $this->threshold('draw_eligibility_emis', $plan->code) === $drawEmis) {
            return;
        }

        $pair[$plan->code] = $pairEmis;
        $draw[$plan->code] = $drawEmis;

        ($this->publishRuleVersion)(
            ['pair_qualification_emis' => $pair, 'draw_eligibility_emis' => $draw],
            $operator,
            "Membership Plans: Pair/Draw EMIs for {$plan->name} ({$plan->code}) set to {$pairEmis}/{$drawEmis}.",
        );
    }

    private function dummyPlanCode(): string
    {
        return (string) $this->rules->value('dummy_entry_plan_code', 'A');
    }

    /** The next free code after the highest one: … H, I, … Z, AA, AB, … Never reuses a code. */
    private function nextCode(): string
    {
        $highest = MembershipPlan::pluck('code')
            ->map(fn (string $code): int => $this->codeToNumber($code))
            ->max() ?? 0;

        return $this->numberToCode($highest + 1);
    }

    private function codeToNumber(string $code): int
    {
        $number = 0;

        foreach (str_split(strtoupper($code)) as $letter) {
            $number = $number * 26 + (ord($letter) - 64);
        }

        return $number;
    }

    private function numberToCode(int $number): string
    {
        $code = '';

        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $code = chr(65 + $remainder).$code;
            $number = intdiv($number - 1, 26);
        }

        return $code;
    }
}

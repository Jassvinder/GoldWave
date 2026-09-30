<?php

namespace App\Services;

use App\Models\Member;

/**
 * DOMAIN_LOGIC.md §7.3 (T-178, 29-09-2026) — how many of a member's Direct Members count toward a Pair/Reward
 * milestone's "Min. Direct Members" total. A direct counts once it is `active` and has a full Pair-eligible joining:
 * a one-time plan once its registration payment is paid, an EMI plan once its paid installments reach that plan's
 * `pair_qualification_emis` (the same thresholds `CreatePairEntries` uses to fan out the direct's own pair entries).
 * Also gates Level Income per level (`CalculateLevelIncome`, `level_income_min_directs`, T-179, DOMAIN_LOGIC.md §6).
 */
class PairQualifiedDirects
{
    public function __construct(private readonly RuleVersionService $rules) {}

    public function count(Member $member): int
    {
        /** @var array<string, int|string|null> $requiredEmis */
        $requiredEmis = (array) $this->rules->value('pair_qualification_emis', []);

        return $member->directs()
            ->where('status', 'active')
            ->with(['membershipPlan', 'emiSchedule' => fn ($query) => $query->withCount(['installments as paid_count' => fn ($q) => $q->where('status', 'paid')])])
            ->withExists(['payments as registration_paid' => fn ($q) => $q->where('type', 'registration')->where('status', 'paid')])
            ->get()
            ->filter(function (Member $direct) use ($requiredEmis): bool {
                $plan = $direct->membershipPlan;

                if ($plan === null) {
                    return false;
                }

                if (! $plan->isEmiPlan()) {
                    return (bool) $direct->getAttribute('registration_paid');
                }

                $required = $requiredEmis[$plan->code] ?? null;

                return $required !== null
                    && $direct->emiSchedule !== null
                    && (int) $direct->emiSchedule->getAttribute('paid_count') >= (int) $required;
            })
            ->count();
    }
}

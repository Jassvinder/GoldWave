<?php

namespace App\Services;

use App\Models\Member;

/**
 * DOMAIN_LOGIC.md §15 (T-183, 30-09-2026) — whether a member earns the upline parts of store income (Purchase/Repurchase
 * L1–12, Store Profit Sponsor L1–3). They do once they have `store_income_min_directs` (default 10) qualified directs;
 * the first time that is true `members.store_income_unlocked_at` is recorded, and from then on it is true for life.
 * With the setting missing or 0 there is no condition and nothing is recorded.
 */
class StoreIncomeUnlock
{
    public function __construct(
        private readonly RuleVersionService $rules,
        private readonly PairQualifiedDirects $qualifiedDirects,
    ) {}

    public function isUnlocked(Member $member): bool
    {
        if ($member->store_income_unlocked_at !== null) {
            return true;
        }

        $required = (int) $this->rules->value('store_income_min_directs', 0);

        if ($required <= 0) {
            return true;
        }

        if ($this->qualifiedDirects->count($member) < $required) {
            return false;
        }

        // Only ever set once, even if two checks race.
        Member::whereKey($member->id)->whereNull('store_income_unlocked_at')->update(['store_income_unlocked_at' => now()]);
        $member->refresh();

        return true;
    }
}

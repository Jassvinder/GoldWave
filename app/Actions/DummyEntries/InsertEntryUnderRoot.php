<?php

namespace App\Actions\DummyEntries;

use App\Models\Member;
use App\Models\MembershipPlan;
use App\Services\RuleVersionService;
use Illuminate\Support\Facades\DB;

/**
 * T-174 (29-09-2026, user decision — DOMAIN_LOGIC.md §2 "System Maintenance") — inserts one entry directly under the
 * company root on the chosen side. It is created with exactly the data a daily dummy entry gets
 * (`GenerateDailyDummyEntries::createEntry`: unassigned company dummy on the dummy plan, sponsor = root), plus
 * `benefits_limited`, so it (and the leader it is later assigned to) earns only Pair/Reward and Income Booster.
 *
 * Whoever sat on that side of the root moves down under the new entry, on the same side — so repeated inserts form
 * a line under the root. Only that one member's placement row changes; every sponsor chain and every subtree stays
 * as it was. The move happens in three steps (unplaced → move the old child → place the new entry) so the
 * `members_placement_unique` constraint is never violated mid-transaction.
 */
class InsertEntryUnderRoot
{
    public function __construct(
        private readonly GenerateDailyDummyEntries $dummyEntries,
        private readonly RuleVersionService $rules,
    ) {}

    /** @param  'left'|'right'  $side */
    public function __invoke(string $side): Member
    {
        $planCode = (string) $this->rules->value('dummy_entry_plan_code', 'A');
        $plan = MembershipPlan::where('code', $planCode)->where('is_active', true)->firstOrFail();

        return DB::transaction(function () use ($side, $plan) {
            $root = Member::where('is_company_root', true)->lockForUpdate()->firstOrFail();

            $oldChild = Member::where('placement_parent_id', $root->id)
                ->where('placement_side', $side)
                ->lockForUpdate()
                ->first();

            $entry = $this->dummyEntries->createEntry($root, $plan, null, null, benefitsLimited: true);

            $oldChild?->update(['placement_parent_id' => $entry->id, 'placement_side' => $side]);

            $entry->update(['placement_parent_id' => $root->id, 'placement_side' => $side]);

            return $entry->fresh() ?? $entry;
        });
    }
}

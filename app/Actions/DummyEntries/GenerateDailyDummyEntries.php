<?php

namespace App\Actions\DummyEntries;

use App\Models\Member;
use App\Services\BinaryPlacementResolver;
use App\Services\CustomerIdGenerator;
use App\Services\RuleVersionService;
use Illuminate\Support\Facades\DB;

/**
 * DOMAIN_LOGIC.md §14.1/§14.2 — reads Super Admin's enable toggle/daily
 * count; if disabled or zero, creates nothing. Otherwise generates that many
 * dummy `members` rows, each placed on the Right side of the seeded company
 * root Member (DOMAIN_LOGIC.md §21 T-013 pre-coding pass, user-confirmed
 * placement anchor) via the same occupied-side-traversal
 * `BinaryPlacementResolver` real registration uses — this naturally
 * continues down whichever node currently sits at the bottom of the chain,
 * whether that's a still-unassigned dummy or an already-assigned real
 * leader, with no separate "most recently assigned leader" tracking needed
 * (§14.3's "continues on the Right side of that assigned leader" falls out
 * automatically). Each entry also gets the root as its `sponsor_id` — the
 * Sponsor/Direct sense of "the company's Direct" — and a real sequential
 * Customer ID (§14.3: "their Customer IDs are genuine and valid").
 */
class GenerateDailyDummyEntries
{
    public function __construct(
        private readonly BinaryPlacementResolver $placementChain,
        private readonly RuleVersionService $rules,
        private readonly CustomerIdGenerator $customerIds,
    ) {}

    /** @return array<int, Member> */
    public function __invoke(): array
    {
        if (! $this->rules->value('dummy_entry_enabled', false)) {
            return [];
        }

        $count = (int) $this->rules->value('dummy_entry_daily_count', 0);

        if ($count <= 0) {
            return [];
        }

        $root = Member::where('is_company_root', true)->firstOrFail();

        return DB::transaction(function () use ($root, $count) {
            $created = [];

            for ($i = 0; $i < $count; $i++) {
                $placement = $this->placementChain->resolve($root, 'right');
                $customerId = $this->customerIds->next();

                $dummy = Member::create([
                    'customer_id' => $customerId,
                    'sponsor_id' => $root->id,
                    'placement_parent_id' => $placement['parent_id'],
                    'placement_side' => $placement['side'],
                    'status' => 'active',
                    'is_company_dummy' => true,
                    'dummy_status' => 'generated',
                    'dummy_generated_at' => now(),
                    'placeholder_name' => "Company Direct — {$customerId}",
                ]);

                $dummy->update(['dummy_status' => 'unassigned']);

                $created[] = $dummy->fresh();
            }

            return $created;
        });
    }
}

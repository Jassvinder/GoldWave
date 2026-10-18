<?php

use App\Models\MembershipPlan;
use Database\Seeders\MembershipPlanSeeder;

/**
 * 14-10-2026 — the one-time plans G/H and the E/F renames existed only on the dev DB, so the first live deploy (a
 * fresh DB seeded from MembershipPlanSeeder) came up without them. A fresh install must have all eight plans.
 */
test('a fresh install seeds all eight plans with their current names', function () {
    $this->seed(MembershipPlanSeeder::class);

    expect(MembershipPlan::orderBy('code')->pluck('name', 'code')->all())->toBe([
        'A' => 'Silver Start',
        'B' => 'Silver Prime',
        'C' => 'Gold Rise',
        'D' => 'Gold Elite',
        'E' => 'Silver Start Direct',
        'F' => 'Gold Rise Direct',
        'G' => 'Silver Prime Direct',
        'H' => 'Gold Elite Direct',
    ])
        ->and(MembershipPlan::whereIn('code', ['E', 'F', 'G', 'H'])->whereNull('installment_count')->count())->toBe(4)
        ->and((float) MembershipPlan::where('code', 'G')->value('amount'))->toBe(30000.0)
        ->and((float) MembershipPlan::where('code', 'H')->value('amount'))->toBe(100000.0);
});

test('T-237: re-running the seeder never overwrites a plan Super Admin edited', function () {
    $this->seed(MembershipPlanSeeder::class);
    MembershipPlan::where('code', 'B')->update(['name' => 'Silver Prime Plus', 'amount' => 2000, 'is_active' => false]);

    $this->seed(MembershipPlanSeeder::class);

    $plan = MembershipPlan::where('code', 'B')->firstOrFail();
    expect($plan->name)->toBe('Silver Prime Plus')
        ->and((float) $plan->amount)->toBe(2000.0)
        ->and($plan->is_active)->toBeFalse()
        ->and(MembershipPlan::count())->toBe(8);
});

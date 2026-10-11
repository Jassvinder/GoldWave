<?php

use App\Models\MembershipPlan;
use Database\Seeders\MembershipPlanSeeder;

/** T-217 — every EMI plan has a one-time Direct twin of the same total (DOMAIN_LOGIC.md §3). */
test('every EMI plan has a one-time Direct twin of the same total', function () {
    $this->seed(MembershipPlanSeeder::class);

    $plans = MembershipPlan::all()->keyBy('code');

    foreach (['A' => 'E', 'B' => 'G', 'C' => 'F', 'D' => 'H'] as $emi => $direct) {
        expect($plans[$direct]->isEmiPlan())->toBeFalse()
            ->and($plans[$direct]->product_category)->toBe($plans[$emi]->product_category)
            ->and((float) $plans[$direct]->amount)->toBe((float) $plans[$emi]->amount * $plans[$emi]->installment_count);
    }

    expect((float) $plans['G']->amount)->toBe(30000.0)
        ->and((float) $plans['H']->amount)->toBe(100000.0);
});

test('the landing page lists Silver then Gold, each EMI plan next to its Direct twin', function () {
    $this->seed(MembershipPlanSeeder::class);

    $this->get('/')->assertInertia(fn ($page) => $page
        ->component('welcome')
        ->where('plans.0.name', 'Silver Start')
        ->where('plans.1.name', 'Silver Start Direct')
        ->where('plans.2.name', 'Silver Prime')
        ->where('plans.3.name', 'Silver Prime Direct')
        ->where('plans.4.name', 'Gold Rise')
        ->where('plans.5.name', 'Gold Rise Direct')
        ->where('plans.6.name', 'Gold Elite')
        ->where('plans.7.name', 'Gold Elite Direct'));
});

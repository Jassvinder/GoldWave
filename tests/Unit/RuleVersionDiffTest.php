<?php

use App\Support\RuleVersionDiff;

test('a scalar rule value change is reported as label: old → new', function () {
    $changes = RuleVersionDiff::summarize(
        ['item_buyback_percent' => 60],
        ['item_buyback_percent' => 58],
    );

    expect($changes)->toBe(['Item Buyback %: 60 → 58']);
});

test('an unchanged scalar produces no line', function () {
    $changes = RuleVersionDiff::summarize(
        ['item_buyback_percent' => 60, 'store_gst_percent' => 0],
        ['item_buyback_percent' => 60, 'store_gst_percent' => 0],
    );

    expect($changes)->toBe([]);
});

test('a changed leaf inside an associative rule value is reported with a readable sub-path', function () {
    $changes = RuleVersionDiff::summarize(
        ['level_income_rates' => ['1' => 5, '2' => 2]],
        ['level_income_rates' => ['1' => 6, '2' => 2]],
    );

    expect($changes)->toBe(['Level Income Rates — 1: 5 → 6']);
});

test('a changed leaf inside a list of rule values is identified by its own milestone/level number, not its array index', function () {
    $old = ['pair_milestones' => [
        ['milestone_no' => 1, 'min_directs' => 2, 'left' => 5, 'right' => 5],
        ['milestone_no' => 2, 'min_directs' => 2, 'left' => 50, 'right' => 50],
    ]];
    $new = ['pair_milestones' => [
        ['milestone_no' => 1, 'min_directs' => 2, 'left' => 5, 'right' => 5],
        ['milestone_no' => 2, 'min_directs' => 2, 'left' => 60, 'right' => 60],
    ]];

    $changes = RuleVersionDiff::summarize($old, $new);

    expect($changes)->toBe([
        'Pair Milestones — #2 Left: 50 → 60',
        'Pair Milestones — #2 Right: 50 → 60',
    ]);
});

test('a brand-new key with no prior value shows every leaf as newly added', function () {
    $changes = RuleVersionDiff::summarize(
        [],
        ['draw_eligibility_emis' => ['A' => 6, 'B' => 2]],
    );

    expect($changes)->toBe([
        'Draw Eligibility EMIs — A: — → 6',
        'Draw Eligibility EMIs — B: — → 2',
    ]);
});

test('a boolean rule value renders as Yes/No, not 1/0', function () {
    $changes = RuleVersionDiff::summarize(
        ['dummy_entry_enabled' => false],
        ['dummy_entry_enabled' => true],
    );

    expect($changes)->toBe(['Dummy Entry Enabled: No → Yes']);
});

<?php

use App\Models\Member;

/**
 * `php artisan test:entries-file` and `test:entries-bulk` (13-10-2026) — many test entries in one run, through the
 * same real registration flow as `test:entries`.
 */
beforeEach(function () {
    $this->seed();
    $this->csv = tempnam(sys_get_temp_dir(), 'entries').'.csv';
});

afterEach(function () {
    @unlink($this->csv);
    @unlink(preg_replace('/\.csv$/', '', $this->csv).'-result.csv');
});

function testEntryMembers()
{
    return Member::with(['user', 'sponsor', 'placementParent', 'membershipPlan'])
        ->whereHas('user', fn ($q) => $q->where('email', 'like', '%@goldwave.test')->where('role', 'member'))
        ->orderBy('id')
        ->get();
}

test('a CSV file adds every entry, resolving #n and PREV sponsors, plan overrides and names', function () {
    file_put_contents($this->csv, "\xEF\xBB\xBFsponsor,side,plan,name\n"
        ."GWL-ROOT,left,,\n"
        ."GWL-ROOT,right,,Ramesh\n"
        ."\n"
        ."#1,left,c,\n"
        ."PREV,Right,,\n");

    $this->artisan('test:entries-file', ['file' => $this->csv])->assertExitCode(0);

    $members = testEntryMembers();
    expect($members)->toHaveCount(4);
    [$first, $second, $third, $fourth] = $members->all();

    expect($first->sponsor->customer_id)->toBe('GWL-ROOT')
        ->and($first->placement_side)->toBe('left')
        ->and($first->membershipPlan->code)->toBe('E')
        ->and($first->user->name)->toBe('Test Member '.(int) preg_replace('/\D/', '', $first->customer_id))
        ->and($second->user->name)->toBe('Ramesh')
        ->and($second->placement_side)->toBe('right')
        ->and($third->sponsor->customer_id)->toBe($first->customer_id)
        ->and($third->membershipPlan->code)->toBe('C')
        ->and($fourth->sponsor->customer_id)->toBe($third->customer_id)
        ->and($fourth->placement_side)->toBe('right');

    $result = array_map('str_getcsv', file(preg_replace('/\.csv$/', '', $this->csv).'-result.csv', FILE_IGNORE_NEW_LINES));
    expect($result[0])->toBe(['entry', 'customer_id', 'name', 'sponsor', 'placed_under', 'side', 'plan'])
        ->and($result)->toHaveCount(5)
        ->and($result[4][1])->toBe($fourth->customer_id);
});

test('a CSV file with any wrong line adds nothing and names every wrong line', function () {
    file_put_contents($this->csv, "GWL-ROOT,left,,\n"
        ."GWL-ROOT,up,,\n"
        ."NOPE99,left,,\n"
        ."#9,left,,\n"
        ."GWL-ROOT,right,Z,\n");

    $this->artisan('test:entries-file', ['file' => $this->csv])
        ->expectsOutputToContain('Entry 2 (line 2): side must be left or right')
        ->expectsOutputToContain('Entry 3 (line 3): sponsor NOPE99 is not an active member')
        ->expectsOutputToContain('Entry 4 (line 4): #9 must point to an entry above this one')
        ->expectsOutputToContain('Entry 5 (line 5): plan "Z" does not exist')
        ->assertExitCode(1);

    expect(testEntryMembers())->toHaveCount(0);
});

test('bulk adds the count under one sponsor on one side, as a chain down that side', function () {
    $this->artisan('test:entries-bulk', ['--under' => 'GWL-ROOT', '--side' => 'left', '--count' => 3])->assertExitCode(0);

    $members = testEntryMembers();
    expect($members)->toHaveCount(3)
        ->and($members->pluck('sponsor.customer_id')->unique()->all())->toBe(['GWL-ROOT'])
        ->and($members->pluck('placement_side')->unique()->all())->toBe(['left'])
        ->and($members[0]->placementParent->customer_id)->toBe('GWL-ROOT')
        ->and($members[1]->placementParent->customer_id)->toBe($members[0]->customer_id)
        ->and($members[2]->placementParent->customer_id)->toBe($members[1]->customer_id);
});

test('bulk refuses wrong options and adds nothing', function () {
    $this->artisan('test:entries-bulk', ['--under' => 'GWL-ROOT', '--side' => 'middle', '--count' => 0])
        ->expectsOutputToContain('--side must be left or right.')
        ->expectsOutputToContain('--count must be a number from 1 to 1000.')
        ->assertExitCode(1);

    expect(testEntryMembers())->toHaveCount(0);
});

test('the test entry commands never run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('test:entries-bulk', ['--under' => 'GWL-ROOT', '--side' => 'left', '--count' => 1])->assertExitCode(1);
    file_put_contents($this->csv, "GWL-ROOT,left,,\n");
    $this->artisan('test:entries-file', ['file' => $this->csv])->assertExitCode(1);

    expect(testEntryMembers())->toHaveCount(0);
});

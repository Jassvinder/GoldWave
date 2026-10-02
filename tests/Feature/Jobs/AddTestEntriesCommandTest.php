<?php

use App\Console\Commands\AddTestEntries;
use App\Models\Member;
use App\Models\User;

/**
 * `php artisan test:entries` (30-09-2026) — each test member is named after its own Customer ID, even when other
 * members rows (e.g. a company placeholder entry) were created in between.
 */
beforeEach(function () {
    $this->seed();
});

test('each entry is named after the Customer ID it received, even after an extra members row', function () {
    $root = Member::where('customer_id', 'GWL-ROOT')->value('customer_id') ?? Member::orderBy('id')->value('customer_id');

    $this->artisan('test:entries', ['entries' => "{$root}:left"])->assertExitCode(0);

    // A members row that is not a test entry (like a System Maintenance placeholder) shifts Member::count().
    Member::create([
        'user_id' => User::factory()->create(['role' => 'member'])->id,
        'customer_id' => 'GWL-PLACEHOLDER',
        'status' => 'active',
        'is_company_dummy' => true,
    ]);

    $this->artisan('test:entries', ['entries' => 'PREV:left,PREV:right'])->assertExitCode(1); // PREV has no entry in a new run.
    $first = Member::where('customer_id', '!=', 'GWL-PLACEHOLDER')->latest('id')->firstOrFail();
    $this->artisan('test:entries', ['entries' => "{$first->customer_id}:left,PREV:right"])->assertExitCode(0);

    Member::with('user')->whereHas('user', fn ($q) => $q->where('name', 'like', 'Test Member %'))->get()
        ->each(fn (Member $member) => expect($member->user->name)
            ->toBe('Test Member '.AddTestEntries::numberOf($member->customer_id)));

    expect(Member::whereHas('user', fn ($q) => $q->where('name', 'like', 'Test Member %'))->count())->toBe(3);
});

test('the Customer ID number drops leading zeros like the existing names', function () {
    expect(AddTestEntries::numberOf('GWL01'))->toBe('1')
        ->and(AddTestEntries::numberOf('GWL17'))->toBe('17')
        ->and(AddTestEntries::numberOf('GWL620'))->toBe('620');
});

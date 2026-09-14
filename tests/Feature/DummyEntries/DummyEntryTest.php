<?php

use App\Actions\DummyEntries\AssignDummyEntryToLeader;
use App\Actions\DummyEntries\GenerateDailyDummyEntries;
use App\Models\Member;
use App\Models\RuleValue;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §14 (Daily Dynamic Company Direct Entries), Docs/TEST.md
 * scenario 14 — generation (disabled/enabled), the fixed root placement
 * anchor, and leader assignment.
 */
function superAdminUser(): User
{
    return User::factory()->create(['role' => 'super_admin']);
}

beforeEach(function () {
    $this->seed();
});

test('generation creates nothing while disabled', function () {
    $created = app(GenerateDailyDummyEntries::class)();

    expect($created)->toBe([]);
    expect(Member::where('is_company_dummy', true)->where('is_company_root', false)->count())->toBe(0);
});

test('enabling with a daily count creates that many dummy entries chained down the root\'s Right side', function () {
    RuleValue::where('key', 'dummy_entry_enabled')->update(['value' => true]);
    RuleValue::where('key', 'dummy_entry_daily_count')->update(['value' => 3]);

    $created = app(GenerateDailyDummyEntries::class)();

    expect($created)->toHaveCount(3);

    $root = Member::where('is_company_root', true)->firstOrFail();

    foreach ($created as $dummy) {
        expect($dummy->is_company_dummy)->toBeTrue();
        expect($dummy->dummy_status)->toBe('unassigned');
        expect($dummy->sponsor_id)->toBe($root->id);
        expect($dummy->placement_side)->toBe('right');
        expect($dummy->customer_id)->not->toBeNull();
        expect($dummy->placeholder_name)->not->toBeNull();
    }

    // Occupied-side traversal: each subsequent entry lands deeper down the same Right chain.
    expect($created[0]->placement_parent_id)->toBe($root->id);
    expect($created[1]->placement_parent_id)->toBe($created[0]->id);
    expect($created[2]->placement_parent_id)->toBe($created[1]->id);
});

test('a second generation run continues the same chain rather than restarting from the root', function () {
    RuleValue::where('key', 'dummy_entry_enabled')->update(['value' => true]);
    RuleValue::where('key', 'dummy_entry_daily_count')->update(['value' => 2]);

    $first = app(GenerateDailyDummyEntries::class)();
    $second = app(GenerateDailyDummyEntries::class)();

    expect($second[0]->placement_parent_id)->toBe($first[1]->id);
});

test('assigning a leader activates the same row, preserves its Customer ID and tree position, and records audit fields', function () {
    RuleValue::where('key', 'dummy_entry_enabled')->update(['value' => true]);
    RuleValue::where('key', 'dummy_entry_daily_count')->update(['value' => 1]);
    $dummy = app(GenerateDailyDummyEntries::class)()[0];
    $originalCustomerId = $dummy->customer_id;
    $originalPlacementParentId = $dummy->placement_parent_id;
    $operator = superAdminUser();

    $assigned = app(AssignDummyEntryToLeader::class)($dummy, 'Real Leader', 'leader@example.test', '9998887777', $operator);

    expect($assigned->id)->toBe($dummy->id);
    expect($assigned->customer_id)->toBe($originalCustomerId);
    expect($assigned->placement_parent_id)->toBe($originalPlacementParentId);
    expect($assigned->status)->toBe('active');
    expect($assigned->dummy_status)->toBe('assigned');
    expect($assigned->dummy_assigned_by)->toBe($operator->id);
    expect($assigned->dummy_assigned_at)->not->toBeNull();
    expect($assigned->placeholder_name)->not->toBeNull(); // Audit history preserved, not overwritten.

    $user = $assigned->user()->firstOrFail();
    expect($user->name)->toBe('Real Leader');
    expect($user->email)->toBe('leader@example.test');
});

test('an already-assigned dummy cannot be assigned again', function () {
    RuleValue::where('key', 'dummy_entry_enabled')->update(['value' => true]);
    RuleValue::where('key', 'dummy_entry_daily_count')->update(['value' => 1]);
    $dummy = app(GenerateDailyDummyEntries::class)()[0];
    app(AssignDummyEntryToLeader::class)($dummy, 'Leader One', 'leader1@example.test', null, superAdminUser());

    expect(fn () => app(AssignDummyEntryToLeader::class)($dummy->fresh(), 'Leader Two', 'leader2@example.test', null, superAdminUser()))
        ->toThrow(ValidationException::class);
});

test('generation after an assignment continues beneath the assigned leader automatically', function () {
    RuleValue::where('key', 'dummy_entry_enabled')->update(['value' => true]);
    RuleValue::where('key', 'dummy_entry_daily_count')->update(['value' => 1]);
    $dummy = app(GenerateDailyDummyEntries::class)()[0];
    $assigned = app(AssignDummyEntryToLeader::class)($dummy, 'Real Leader', 'leader@example.test', null, superAdminUser());

    $next = app(GenerateDailyDummyEntries::class)()[0];

    expect($next->placement_parent_id)->toBe($assigned->id);
});

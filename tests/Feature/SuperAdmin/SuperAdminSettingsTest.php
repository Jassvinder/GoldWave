<?php

use App\Actions\Admin\CreateAdminUser;
use App\Actions\Draw\ExecuteMonthlyDraw;
use App\Actions\Draw\GenerateDrawGroups;
use App\Actions\Draw\ReconcileDrawExecution;
use App\Actions\Settings\PublishRuleVersion;
use App\Actions\Settings\SetMetalRate;
use App\Actions\Store\CreateStore;
use App\Actions\Store\ReassignStoreOwner;
use App\Models\DrawExecutionCorrection;
use App\Models\DrawGroup;
use App\Models\DrawGroupMonthConfig;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\RuleValue;
use App\Models\RuleVersion;
use App\Models\StoreActivityLog;
use App\Models\User;
use App\Services\RuleVersionService;
use Illuminate\Validation\ValidationException;

/**
 * T-017 pre-coding pass (`DOMAIN_LOGIC.md` §21) — the 5 previously-missing
 * Super Admin write-paths: versioned settings publishing, Admin user
 * creation, Store Owner reassignment, metal rate entry, and draw
 * reconciliation.
 */
function saSuperAdmin(): User
{
    return User::where('role', 'super_admin')->firstOrFail();
}

function saSettingsMember(string $customerId): Member
{
    $user = User::factory()->create(['role' => 'member']);
    $plan = MembershipPlan::where('code', 'E')->first();

    return Member::create([
        'user_id' => $user->id,
        'customer_id' => $customerId,
        'membership_plan_id' => $plan?->id,
        'status' => 'active',
        'activated_at' => now(),
    ]);
}

function saDrawGroupOf(int $groupSize, int $memberCount, string $prefix): DrawGroup
{
    RuleValue::where('key', 'draw_group_size')->update(['value' => $groupSize]);

    for ($i = 1; $i <= $memberCount; $i++) {
        saSettingsMember(sprintf('%s%03d', $prefix, $i));
    }

    return app(GenerateDrawGroups::class)()[0];
}

beforeEach(function () {
    $this->seed();
});

test('publishing a rule version carries every prior key forward and overlays only the changed ones', function () {
    $active = RuleVersion::where('is_active', true)->firstOrFail();
    $previousKeyCount = $active->values()->count();
    $previousDrawGroupSize = RuleValue::where('rule_version_id', $active->id)->where('key', 'draw_group_size')->value('value');

    $newVersion = app(PublishRuleVersion::class)(['payout_min_amount' => 750], saSuperAdmin(), 'Raise payout minimum');

    expect($newVersion->is_active)->toBeTrue();
    expect($active->fresh()->is_active)->toBeFalse();
    expect($newVersion->version_no)->toBe($active->version_no + 1);
    expect($newVersion->values()->count())->toBe($previousKeyCount);
    expect($newVersion->values()->where('key', 'payout_min_amount')->value('value'))->toBe(750);
    expect($newVersion->values()->where('key', 'draw_group_size')->value('value'))->toBe($previousDrawGroupSize);
});

test('a published rule version is immediately visible, not cached from the old active version', function () {
    app(PublishRuleVersion::class)(['payout_min_amount' => 999], saSuperAdmin());

    expect(app(RuleVersionService::class)->value('payout_min_amount'))->toBe(999);
});

test('creating an admin user sets role=admin and a usable password', function () {
    $user = app(CreateAdminUser::class)('New Owner', 'newowner@goldwave.test', 'AdminPass123');

    expect($user->role)->toBe('admin');
    expect($user->fresh()->password)->not->toBe('AdminPass123'); // hashed, not stored raw.
});

test('reassigning a store owner updates ownership and logs an activity entry', function () {
    $originalOwner = User::factory()->create(['role' => 'admin']);
    $newOwner = User::factory()->create(['role' => 'admin']);
    $store = app(CreateStore::class)('Reassign Test Store', $originalOwner, null, null, 100000, 0, saSuperAdmin());

    app(ReassignStoreOwner::class)($store, $newOwner, saSuperAdmin());

    expect($store->fresh()->owner_user_id)->toBe($newOwner->id);
    expect(StoreActivityLog::where('store_id', $store->id)->where('action_type', 'store_owner_reassigned')->exists())->toBeTrue();
});

test('setting a metal rate creates a new effective-dated row without touching prior rates', function () {
    $first = app(SetMetalRate::class)('gold', 6000, now()->subDay()->toDateString(), saSuperAdmin());
    $second = app(SetMetalRate::class)('gold', 6200, now()->toDateString(), saSuperAdmin());

    expect($first->fresh()->rate_per_gram)->toBe('6000.00');
    expect($second->rate_per_gram)->toBe('6200.00');
});

test('reconciling an executed draw sets status and metadata without touching the winner', function () {
    $group = saDrawGroupOf(5, 5, 'REC');
    DrawGroupMonthConfig::create([
        'draw_group_id' => $group->id,
        'cycle_month_no' => 1,
        'prize_name' => 'Silver prize',
        'prize_value' => 5000,
        'metal_type' => 'silver',
    ]);
    $execution = app(ExecuteMonthlyDraw::class)()[0];
    $originalWinnerId = $execution->winner_member_id;

    $reconciled = app(ReconcileDrawExecution::class)($execution, saSuperAdmin());

    expect($reconciled->status)->toBe('reconciled');
    expect($reconciled->reconciled_at)->not->toBeNull();
    expect($reconciled->reconciled_by)->toBe(saSuperAdmin()->id);
    expect($reconciled->winner_member_id)->toBe($originalWinnerId);
});

test('a correction note on reconciliation creates a separate audit record, not an edit to the execution', function () {
    $group = saDrawGroupOf(5, 5, 'COR');
    DrawGroupMonthConfig::create([
        'draw_group_id' => $group->id,
        'cycle_month_no' => 1,
        'prize_name' => 'Silver prize',
        'prize_value' => 5000,
        'metal_type' => 'silver',
    ]);
    $execution = app(ExecuteMonthlyDraw::class)()[0];

    app(ReconcileDrawExecution::class)($execution, saSuperAdmin(), 'Prize was handed over a day late.');

    $correction = DrawExecutionCorrection::where('draw_execution_id', $execution->id)->firstOrFail();
    expect($correction->note)->toBe('Prize was handed over a day late.');
    expect($correction->created_by)->toBe(saSuperAdmin()->id);
});

test('an already-reconciled draw cannot be reconciled again', function () {
    $group = saDrawGroupOf(5, 5, 'DUP');
    DrawGroupMonthConfig::create([
        'draw_group_id' => $group->id,
        'cycle_month_no' => 1,
        'prize_name' => 'Silver prize',
        'prize_value' => 5000,
        'metal_type' => 'silver',
    ]);
    $execution = app(ExecuteMonthlyDraw::class)()[0];
    app(ReconcileDrawExecution::class)($execution, saSuperAdmin());

    expect(fn () => app(ReconcileDrawExecution::class)($execution->fresh(), saSuperAdmin()))
        ->toThrow(ValidationException::class);
});

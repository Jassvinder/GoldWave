<?php

use App\Actions\Draw\ExecuteMonthlyDraw;
use App\Actions\Draw\GenerateDrawGroups;
use App\Actions\DummyEntries\GenerateDailyDummyEntries;
use App\Actions\Store\CreateStore;
use App\Models\DrawGroupMonthConfig;
use App\Models\IncomeLedgerCalculation;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\MetalRate;
use App\Models\RuleValue;
use App\Models\RuleVersion;
use App\Models\Store;
use App\Models\User;
use App\Services\RuleVersionService;

/**
 * T-017 — HTTP-layer coverage (routes, role gating, Form Requests) for the
 * Super Admin Portal's controllers built around T-017's pre-coding-pass
 * Actions. The Actions themselves are covered directly (not via HTTP) in
 * `tests/Feature/SuperAdmin/SuperAdminSettingsTest.php` — this file exercises
 * the routes/controllers wrapping them instead of duplicating that coverage.
 */
function spSuperAdmin(): User
{
    return User::where('role', 'super_admin')->firstOrFail();
}

function spMember(string $customerId): Member
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

beforeEach(function () {
    $this->seed();
});

test('a non-super-admin is forbidden from every super admin portal route', function () {
    $member = spMember('SP-MEMBER');

    $this->actingAs($member->user)->get('/super-admin/admin-users')->assertForbidden();
});

test('a super admin sees the real S01 System Dashboard at the shared /dashboard route', function () {
    $response = $this->actingAs(spSuperAdmin())->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('super-admin/dashboard'));
});

test('a super admin can create an admin user (S02)', function () {
    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/admin-users', [
            'name' => 'New Store Owner',
            'email' => 'newstoreowner@goldwave.test',
            'password' => 'AdminPass123',
        ])
        ->assertRedirect('/super-admin/admin-users');

    expect(User::where('email', 'newstoreowner@goldwave.test')->where('role', 'admin')->exists())->toBeTrue();
});

test('a super admin can publish a compensation rule version (S03)', function () {
    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/rule-versions', [
            'item_buyback_percent' => 58,
            'notes' => 'Tuned per client feedback.',
        ])
        ->assertRedirect('/super-admin/rule-versions');

    $active = RuleVersion::where('is_active', true)->firstOrFail();
    expect((float) $active->values()->where('key', 'item_buyback_percent')->value('value'))->toBe(58.0);
});

test('a super admin can update dummy entry settings and trigger generation (S04)', function () {
    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/dummy-entry-settings', ['enabled' => true, 'daily_count' => 2])
        ->assertRedirect('/super-admin/dummy-entry-settings');

    expect((int) app(RuleVersionService::class)->value('dummy_entry_daily_count'))->toBe(2);

    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/dummy-entry-settings/generate')
        ->assertRedirect('/super-admin/dummy-entry-settings');

    expect(Member::where('is_company_dummy', true)->where('is_company_root', false)->count())->toBe(2);
});

test('a super admin can assign a leader to a dummy entry (S05)', function () {
    RuleValue::where('key', 'dummy_entry_enabled')->update(['value' => true]);
    RuleValue::where('key', 'dummy_entry_daily_count')->update(['value' => 1]);
    $dummy = app(GenerateDailyDummyEntries::class)()[0];

    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/dummy-entry-assignment', [
            'member_id' => $dummy->id,
            'name' => 'Real Leader',
            'email' => 'realleader@goldwave.test',
            'mobile' => '9998887771',
        ])
        ->assertRedirect('/super-admin/dummy-entry-assignment');

    expect($dummy->fresh()->dummy_status)->toBe('assigned');
});

test('a super admin can update draw settings and reconcile an executed draw (S06)', function () {
    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/draw-settings', ['draw_group_size' => 5])
        ->assertRedirect('/super-admin/draw-settings');

    expect((int) app(RuleVersionService::class)->value('draw_group_size'))->toBe(5);

    for ($i = 1; $i <= 5; $i++) {
        spMember(sprintf('SPDRAW%03d', $i));
    }
    $group = app(GenerateDrawGroups::class)()[0];
    DrawGroupMonthConfig::create([
        'draw_group_id' => $group->id,
        'cycle_month_no' => 1,
        'prize_name' => 'Silver prize',
        'prize_value' => 5000,
        'metal_type' => 'silver',
    ]);
    $execution = app(ExecuteMonthlyDraw::class)()[0];

    $this->actingAs(spSuperAdmin())
        ->get('/super-admin/draw-management')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('super-admin/draw-management'));

    $this->actingAs(spSuperAdmin())
        ->post("/super-admin/draw-management/{$execution->id}/reconcile", ['correction_note' => 'Verified in person.'])
        ->assertRedirect('/super-admin/draw-management');

    expect($execution->fresh()->status)->toBe('reconciled');
});

test('a super admin can record a new metal rate (S07)', function () {
    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/metal-rates', [
            'metal' => 'gold',
            'rate_per_gram' => 6500,
            'effective_from' => now()->toDateString(),
        ])
        ->assertRedirect('/super-admin/metal-rates');

    expect(MetalRate::where('metal', 'gold')->where('rate_per_gram', 6500)->exists())->toBeTrue();
});

test('a super admin can update payout and TDS settings (S08)', function () {
    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/payout-tds-settings', [
            'payout_min_amount' => 1000,
            'payout_tds_percent' => 5,
            'payout_processing_fee_percent' => 1,
        ])
        ->assertRedirect('/super-admin/payout-tds-settings');

    expect((float) app(RuleVersionService::class)->value('payout_min_amount'))->toBe(1000.0);
});

test('a super admin can create a store, reassign its owner, and change its status (S09)', function () {
    $owner = User::factory()->create(['role' => 'admin']);

    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/store-management', [
            'name' => 'S09 Test Store',
            'owner_user_id' => $owner->id,
            'jewellery_allocation_value' => 100000,
            'advance_amount' => 20000,
        ])
        ->assertRedirect('/super-admin/store-management');

    $store = Store::where('name', 'S09 Test Store')->firstOrFail();
    expect($store->owner_user_id)->toBe($owner->id);

    $newOwner = User::factory()->create(['role' => 'admin']);

    $this->actingAs(spSuperAdmin())
        ->post("/super-admin/store-management/{$store->id}/reassign-owner", ['owner_user_id' => $newOwner->id])
        ->assertRedirect("/super-admin/store-management/{$store->id}");

    expect($store->fresh()->owner_user_id)->toBe($newOwner->id);

    $this->actingAs(spSuperAdmin())
        ->post("/super-admin/store-management/{$store->id}/status", ['status' => 'inactive'])
        ->assertRedirect("/super-admin/store-management/{$store->id}");

    expect($store->fresh()->status)->toBe('inactive');

    $this->actingAs(spSuperAdmin())
        ->get("/super-admin/store-management/{$store->id}")
        ->assertOk();
});

test('a super admin can top up a store wallet (S10)', function () {
    $store = app(CreateStore::class)('S10 Test Store', null, null, null, 50000, 0, spSuperAdmin());

    $this->actingAs(spSuperAdmin())
        ->post("/super-admin/store-wallets/{$store->id}/topup", ['amount' => 15000, 'description' => 'Owner top-up.'])
        ->assertRedirect("/super-admin/store-wallets/{$store->id}");

    expect((float) $store->wallet->fresh()->balance)->toBe(15000.0);

    $this->actingAs(spSuperAdmin())
        ->get('/super-admin/store-wallets')
        ->assertOk();
});

test('a super admin can view the member list, a member detail page, and export members (Admin Member Management)', function () {
    $member = spMember('SPMEMLIST');

    $this->actingAs(spSuperAdmin())
        ->get('/super-admin/members')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('super-admin/member-management'));

    $this->actingAs(spSuperAdmin())
        ->get("/super-admin/members/{$member->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('super-admin/member-detail')
            ->where('member.customer_id', 'SPMEMLIST'));

    $response = $this->actingAs(spSuperAdmin())->get('/super-admin/members/export');
    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

test('a super admin can view the compensation audit page, and the config page redirects to rule versions (Admin Compensation Management)', function () {
    $member = spMember('SPCOMPAUDIT');
    IncomeLedgerCalculation::create([
        'type' => 'level_income',
        'beneficiary_member_id' => $member->id,
        'level_no' => 1,
        'rate_percent' => 5,
        'amount' => 500,
        'rule_version_id' => RuleVersion::where('is_active', true)->firstOrFail()->id,
        'eligibility_status' => 'paid',
    ]);

    $this->actingAs(spSuperAdmin())
        ->get('/super-admin/compensation/audit')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('super-admin/compensation-audit'));

    $this->actingAs(spSuperAdmin())
        ->get('/super-admin/compensation/config')
        ->assertRedirect('/super-admin/rule-versions');
});

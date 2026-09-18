<?php

use App\Actions\Draw\ExecuteMonthlyDraw;
use App\Actions\Draw\GenerateDrawGroups;
use App\Actions\DummyEntries\GenerateDailyDummyEntries;
use App\Actions\Payout\SubmitPayoutRequest;
use App\Actions\Profile\SubmitProfileChangeRequest;
use App\Actions\Store\CreateStore;
use App\Models\DrawGroupMonthConfig;
use App\Models\IncomeLedgerCalculation;
use App\Models\Member;
use App\Models\MemberBankDetail;
use App\Models\MembershipPlan;
use App\Models\MetalRate;
use App\Models\RuleValue;
use App\Models\RuleVersion;
use App\Models\Store;
use App\Models\User;
use App\Services\RuleVersionService;
use App\Services\WalletLedgerService;

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

test('a super admin can find and promote an existing member to admin (S02)', function () {
    $member = spMember('SP-PROMOTE');

    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/admin-users/find-member', ['customer_id' => 'SP-PROMOTE'])
        ->assertOk()
        ->assertJson(['valid' => true, 'member_customer_id' => 'SP-PROMOTE']);

    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/admin-users', ['customer_id' => 'SP-PROMOTE'])
        ->assertRedirect('/super-admin/admin-users');

    expect($member->user->fresh()->role)->toBe('admin');
});

test('promoting a dummy or already-admin customer ID is rejected (S02)', function () {
    $dummy = spMember('SP-DUMMY-2');
    $dummy->update(['is_company_dummy' => true]);

    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/admin-users/find-member', ['customer_id' => 'SP-DUMMY-2'])
        ->assertStatus(422)
        ->assertJson(['valid' => false]);

    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/admin-users', ['customer_id' => 'SP-DUMMY-2'])
        ->assertSessionHasErrors('customer_id');
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

test('Version History shows what actually changed, not just the optional note (T-108)', function () {
    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/rule-versions', ['item_buyback_percent' => 58]);

    $this->actingAs(spSuperAdmin())
        ->get('/super-admin/rule-versions')
        ->assertInertia(fn ($page) => $page
            ->component('super-admin/rule-versions')
            ->where('versions.0.changes', ['Item Buyback %: 60 → 58'])
            ->where('versions.1.changes', null));
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

test('a super admin can directly edit a member\'s details, including a new bank account (T-106)', function () {
    $member = spMember('SPMEMEDIT');

    $this->actingAs(spSuperAdmin())
        ->patch("/super-admin/members/{$member->id}", [
            'name' => 'Corrected Name',
            'email' => 'corrected@goldwave.test',
            'mobile' => '9998887776',
            'pan_card' => 'ABCDE1234F',
            'aadhaar_card' => '123456789012',
            'address' => '221B Baker Street',
            'bank_account_holder_name' => 'Corrected Name',
            'bank_account_number' => '000111222333',
            'bank_ifsc_code' => 'HDFC0001234',
            'bank_name' => 'HDFC Bank',
        ])
        ->assertRedirect("/super-admin/members/{$member->id}");

    $member->refresh();
    expect($member->user->name)->toBe('Corrected Name');
    expect($member->user->email)->toBe('corrected@goldwave.test');
    expect($member->pan_card)->toBe('ABCDE1234F');
    expect($member->pending_fields_submitted_at)->not->toBeNull();

    $bankDetail = $member->bankDetails()->latest('id')->firstOrFail();
    expect($bankDetail->account_number)->toBe('000111222333');
    expect($bankDetail->verified_at)->toBeNull();
});

test('re-submitting a member\'s unchanged bank details does not reset an already-verified account', function () {
    $member = spMember('SPMEMBANKOK');
    $bankDetail = MemberBankDetail::create([
        'member_id' => $member->id,
        'account_holder_name' => 'Same Name',
        'account_number' => '555666777',
        'ifsc_code' => 'ICIC0009999',
        'bank_name' => 'ICICI Bank',
        'verified_by' => spSuperAdmin()->id,
        'verified_at' => now(),
    ]);

    $this->actingAs(spSuperAdmin())
        ->patch("/super-admin/members/{$member->id}", [
            'name' => $member->user->name,
            'email' => $member->user->email,
            'bank_account_holder_name' => 'Same Name',
            'bank_account_number' => '555666777',
            'bank_ifsc_code' => 'ICIC0009999',
            'bank_name' => 'ICICI Bank',
        ])
        ->assertRedirect("/super-admin/members/{$member->id}");

    expect($bankDetail->fresh()->verified_at)->not->toBeNull();
});

test('a super admin can edit an admin user\'s own details (T-106)', function () {
    $admin = User::factory()->create(['role' => 'admin', 'email' => 'oldadminemail@goldwave.test']);

    $this->actingAs(spSuperAdmin())
        ->patch("/super-admin/admin-users/{$admin->id}", [
            'name' => 'Renamed Admin',
            'email' => 'newadminemail@goldwave.test',
            'mobile' => '9123456780',
        ])
        ->assertRedirect('/super-admin/admin-users');

    expect($admin->fresh()->name)->toBe('Renamed Admin');
    expect($admin->fresh()->email)->toBe('newadminemail@goldwave.test');
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

test('a super admin can view and process a pending payout request (T-109)', function () {
    $member = spMember('SPPAYOUT1');
    app(WalletLedgerService::class)->credit($member, 'level_income', 20000, null, 'seed credit');
    $bankDetail = MemberBankDetail::create([
        'member_id' => $member->id,
        'account_holder_name' => 'SP Payout Holder',
        'account_number' => '1112223334',
        'ifsc_code' => 'TEST0009999',
        'bank_name' => 'Test Bank',
        'verified_at' => now(),
    ]);
    $payoutRequest = app(SubmitPayoutRequest::class)($member->fresh(), 10000, $bankDetail);

    $this->actingAs(spSuperAdmin())
        ->get('/super-admin/payout-requests')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('super-admin/payout-requests')
            ->where('pending.0.member.customer_id', 'SPPAYOUT1'));

    $this->actingAs(spSuperAdmin())
        ->post("/super-admin/payout-requests/{$payoutRequest->id}/process", [
            'method' => 'bank_transfer',
            'reference' => 'UTR123',
        ])
        ->assertRedirect();

    expect($payoutRequest->fresh()->status)->toBe('processed');
});

test('a super admin can reject a pending payout request, releasing its hold (T-109)', function () {
    $member = spMember('SPPAYOUT2');
    app(WalletLedgerService::class)->credit($member, 'level_income', 20000, null, 'seed credit');
    $bankDetail = MemberBankDetail::create([
        'member_id' => $member->id,
        'account_holder_name' => 'SP Payout Holder 2',
        'account_number' => '5556667778',
        'ifsc_code' => 'TEST0008888',
        'bank_name' => 'Test Bank',
        'verified_at' => now(),
    ]);
    $payoutRequest = app(SubmitPayoutRequest::class)($member->fresh(), 3000, $bankDetail);

    $this->actingAs(spSuperAdmin())
        ->post("/super-admin/payout-requests/{$payoutRequest->id}/reject")
        ->assertRedirect();

    expect($payoutRequest->fresh()->status)->toBe('rejected');
});

test('a super admin can view and approve a pending profile change request (T-109)', function () {
    $member = spMember('SPCHANGEREQ1');
    $member->update(['pending_fields_submitted_at' => now()]);
    $changeRequest = app(SubmitProfileChangeRequest::class)($member->fresh(), 'address', 'New Address, City', 'Moved house');

    $this->actingAs(spSuperAdmin())
        ->get('/super-admin/profile-change-requests')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('super-admin/profile-change-requests')
            ->where('pending.0.member.customer_id', 'SPCHANGEREQ1')
            ->where('pending.0.new_value', 'New Address, City'));

    $this->actingAs(spSuperAdmin())
        ->post("/super-admin/profile-change-requests/{$changeRequest->id}/approve")
        ->assertRedirect();

    expect($changeRequest->fresh()->status)->toBe('approved');
    expect($member->fresh()->address)->toBe('New Address, City');
});

test('a super admin can reject a pending profile change request with a reason, leaving the field untouched (T-109)', function () {
    $member = spMember('SPCHANGEREQ2');
    $member->update(['pending_fields_submitted_at' => now(), 'address' => 'Original Address']);
    $changeRequest = app(SubmitProfileChangeRequest::class)($member->fresh(), 'address', 'Disputed Address');

    $this->actingAs(spSuperAdmin())
        ->post("/super-admin/profile-change-requests/{$changeRequest->id}/reject", [
            'rejection_reason' => 'Could not verify new address.',
        ])
        ->assertRedirect();

    expect($changeRequest->fresh()->status)->toBe('rejected');
    expect($changeRequest->fresh()->rejection_reason)->toBe('Could not verify new address.');
    expect($member->fresh()->address)->toBe('Original Address');
});

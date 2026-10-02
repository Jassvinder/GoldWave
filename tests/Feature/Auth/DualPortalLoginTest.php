<?php

use App\Actions\Store\CreateStore;
use App\Models\Member;
use App\Models\User;
use App\Services\Otp\EmailOtpChannel;
use App\Services\Otp\SmsOtpChannel;
use Tests\Fakes\RecordingOtpChannel;

/**
 * T-131 — Docs/TEST.md scenario 20. A Store Owner who is also a Member has one
 * `users` row (role=admin) but two separate logins that open two separate
 * portals: Member login => Member Portal, Store ID login => Store Portal.
 */
function dualOwner(string $customerId = 'DUAL-1', string $storePassword = 'StorePass123!'): array
{
    $owner = User::factory()->create(['role' => 'store_admin', 'password' => $customerId, 'mobile' => '9876500777']);
    $member = Member::create([
        'user_id' => $owner->id,
        'customer_id' => $customerId,
        'status' => 'active',
        'activated_at' => now(),
    ]);
    $store = app(CreateStore::class)('Dual Jewellers', $owner, null, null, 0, 0, User::factory()->create(['role' => 'super_admin']), $storePassword);

    return compact('owner', 'member', 'store');
}

beforeEach(function () {
    RecordingOtpChannel::$sent = [];
    $this->app->bind(EmailOtpChannel::class, RecordingOtpChannel::class);
    $this->app->bind(SmsOtpChannel::class, RecordingOtpChannel::class);
});

test('a Store Owner who is also a Member enters the Member Portal via the Member login, and is kept out of the Store Portal (T-131)', function () {
    ['owner' => $owner] = dualOwner();

    $this->post('/member/login/password', ['customer_id' => 'DUAL-1', 'password' => 'DUAL-1'])->assertRedirect();
    $this->assertAuthenticatedAs($owner);

    $this->get('/dashboard')->assertOk()->assertInertia(fn ($page) => $page->component('member/dashboard'));
    $this->get('/member/profile')->assertOk();
    $this->get('/member/directs')->assertOk();
    $this->get('/member/tree')->assertOk();
    $this->get('/admin/sales')->assertForbidden();
});

test('the same owner logging in with Store ID and password lands in the Store Portal, not the Member Portal (T-131)', function () {
    ['owner' => $owner, 'store' => $store] = dualOwner();

    $this->post('/login/store', ['store_code' => $store->store_code, 'password' => 'StorePass123!'])->assertRedirect();
    $this->assertAuthenticatedAs($owner);

    // Regression: DashboardController used to check `$user->member` first, so this rendered the member dashboard.
    $this->get('/dashboard')->assertOk()->assertInertia(fn ($page) => $page->component('admin/dashboard'));
    $this->get('/admin/sales')->assertOk();
    $this->get('/member/profile')->assertForbidden();
    $this->get('/member/directs')->assertForbidden();
});

test('OTP login is also a Member-portal login for a Store Owner who is a Member (T-131)', function () {
    dualOwner();

    $this->post('/member/login/otp/request', ['identifier' => '9876500777'])->assertSessionHasNoErrors();
    $code = RecordingOtpChannel::$sent['9876500777'];
    $this->post('/member/login/otp/verify', ['identifier' => '9876500777', 'otp' => $code])->assertRedirect();

    $this->get('/member/profile')->assertOk();
    $this->get('/admin/sales')->assertForbidden();
});

test('the portal is set at every login — logging in through the other door flips it (T-131)', function () {
    ['store' => $store] = dualOwner();

    $this->post('/member/login/password', ['customer_id' => 'DUAL-1', 'password' => 'DUAL-1'])->assertRedirect();
    $this->get('/member/profile')->assertOk();

    auth()->logout();
    $this->flushSession();

    $this->post('/login/store', ['store_code' => $store->store_code, 'password' => 'StorePass123!'])->assertRedirect();
    $this->get('/member/profile')->assertForbidden();
    $this->get('/admin/sales')->assertOk();
});

test('an admin with a member row but no login stamp cannot use Member Portal routes (T-131)', function () {
    ['owner' => $owner] = dualOwner();

    $this->actingAs($owner)->get('/member/profile')->assertForbidden();
    $this->actingAs($owner)->get('/dashboard')->assertOk()->assertInertia(fn ($page) => $page->component('admin/dashboard'));
});

test('plain members, admins without a member row, and Super Admin are unaffected by the portal stamp (T-131)', function () {
    $memberUser = User::factory()->create(['role' => 'member']);
    Member::create(['user_id' => $memberUser->id, 'customer_id' => 'DUAL-PLAIN', 'status' => 'active', 'activated_at' => now()]);
    $this->actingAs($memberUser)->get('/member/profile')->assertOk();
    $this->actingAs($memberUser)->get('/admin/sales')->assertForbidden();

    $storeOnly = User::factory()->create(['role' => 'store_admin']);
    app(CreateStore::class)('Store Only', $storeOnly, null, null, 0, 0, User::factory()->create(['role' => 'super_admin']), 'StorePass123!');
    $this->actingAs($storeOnly)->withSession(['goldwave_portal' => 'member'])->get('/member/profile')->assertForbidden();

    $superAdmin = User::factory()->create(['role' => 'super_admin']);
    $target = Member::create(['user_id' => User::factory()->create(['role' => 'member'])->id, 'customer_id' => 'DUAL-T', 'status' => 'active', 'activated_at' => now()]);
    $this->actingAs($superAdmin)->get("/member/directs/{$target->id}")->assertOk();
    $this->actingAs($superAdmin)->withSession(['goldwave_portal' => 'member'])->get('/super-admin/members')->assertOk();
});

test('an admin in a Member-portal session cannot reach Super Admin routes (T-131)', function () {
    ['owner' => $owner] = dualOwner();

    $this->actingAs($owner)->withSession(['goldwave_portal' => 'member'])->get('/super-admin/members')->assertForbidden();
});

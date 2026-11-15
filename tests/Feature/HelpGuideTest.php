<?php

use App\Actions\Store\CreateStore;
use App\Models\Member;
use App\Models\User;

/** T-244 — each portal opens its own Hinglish guide page inside its own layout; other roles can't open it. */
beforeEach(function () {
    $this->seed();
});

test('member, store admin and company staff each open their own guide', function () {
    $member = Member::create([
        'user_id' => User::factory()->create(['role' => 'member'])->id,
        'customer_id' => 'GWL900',
        'status' => 'active',
        'activated_at' => now(),
    ]);
    $superAdmin = User::where('role', 'super_admin')->firstOrFail();
    $companyAdmin = User::factory()->create(['role' => 'admin']);
    $storeAdmin = User::factory()->create(['role' => 'store_admin']);
    app(CreateStore::class)('Help Store', $storeAdmin, '9998887766', 'Test City', 1000000, 50000, $superAdmin);

    $this->actingAs($member->user)->get('/member/help')->assertOk()->assertInertia(fn ($page) => $page->component('member/help'));
    $this->actingAs($storeAdmin)->get('/admin/help')->assertOk()->assertInertia(fn ($page) => $page->component('admin/help'));
    $this->actingAs($superAdmin)->get('/super-admin/help')->assertOk()->assertInertia(fn ($page) => $page->component('super-admin/help'));
    $this->actingAs($companyAdmin)->get('/super-admin/help')->assertOk();

    $this->actingAs($member->user)->get('/super-admin/help')->assertForbidden();
    $this->actingAs($member->user)->get('/admin/help')->assertForbidden();
    $this->actingAs($storeAdmin)->get('/super-admin/help')->assertForbidden();
});

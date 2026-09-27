<?php

use App\Actions\Store\CreateStore;
use App\Models\User;

/**
 * T-117 (19-09-2026) — user's own explicit dual-login split: Admin/Store
 * Login (Store ID + Store password) is a fully separate flow from both the
 * generic Fortify `/login` (now Super-Admin-only) and the Member's own
 * Customer-ID login.
 */
function loginStoreOwner(string $storeName, string $password): array
{
    $owner = User::factory()->create(['role' => 'admin']);
    $store = app(CreateStore::class)($storeName, $owner, null, null, 0, 0, User::factory()->create(['role' => 'super_admin']), $password);

    return ['owner' => $owner, 'store' => $store];
}

test('a Store Owner can log in with the Store ID and password (T-117)', function () {
    ['owner' => $owner, 'store' => $store] = loginStoreOwner('T117 Store', 'CorrectPass123!');

    $this->post('/login/store', [
        'store_code' => $store->store_code,
        'password' => 'CorrectPass123!',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($owner);
});

test('Admin/Store Login rejects a wrong Store ID or a wrong password with the same generic message', function () {
    ['store' => $store] = loginStoreOwner('T117 Store Wrong Pw', 'CorrectPass123!');

    $this->post('/login/store', ['store_code' => $store->store_code, 'password' => 'WrongPass!'])
        ->assertSessionHasErrors('store_code');
    $this->assertGuest();

    $this->post('/login/store', ['store_code' => 'GWLST9999', 'password' => 'CorrectPass123!'])
        ->assertSessionHasErrors('store_code');
    $this->assertGuest();
});

test('a store with no owner yet cannot be logged into even with its own Store ID', function () {
    $store = app(CreateStore::class)('No Owner Yet Store', null, null, null, 0, 0, User::factory()->create(['role' => 'super_admin']));

    $this->post('/login/store', ['store_code' => $store->store_code, 'password' => 'anything'])
        ->assertSessionHasErrors('store_code');
    $this->assertGuest();
});

test('an Admin (Store Owner) cannot use the generic Super Admin email+password login, even with the correct password', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password'])
        ->assertSessionHasErrors();
    $this->assertGuest();
});

test('a Super Admin can still log in via the generic email+password login, unaffected by the Admin/Store split', function () {
    $superAdmin = User::factory()->create(['role' => 'super_admin']);

    $this->post(route('login.store'), ['email' => $superAdmin->email, 'password' => 'password'])
        ->assertRedirect();

    $this->assertAuthenticatedAs($superAdmin);
});

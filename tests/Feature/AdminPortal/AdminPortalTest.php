<?php

use App\Actions\Store\AllocateStoreInventoryItem;
use App\Actions\Store\CreateStore;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\MetalRate;
use App\Models\ProductBenefit;
use App\Models\Store;
use App\Models\StoreActivityLog;
use App\Models\StoreSale;
use App\Models\User;

/**
 * INSTRUCTIONS.md's Admin / Store Owner Portal (T-016) — the HTTP layer
 * (routes, `EnsureStoreOwnership` middleware, Form Requests) around T-014's
 * already-built Store Actions, which had no UI/controller surface before
 * this task.
 */
function adminOperator(): User
{
    return User::where('role', 'super_admin')->firstOrFail();
}

function adminPortalMember(string $customerId): Member
{
    $user = User::factory()->create(['role' => 'member']);

    return Member::create([
        'user_id' => $user->id,
        'customer_id' => $customerId,
        'status' => 'active',
        'activated_at' => now(),
    ]);
}

/** @return array{admin: User, store: Store} */
function makeAdminWithStore(string $prefix): array
{
    $adminUser = User::factory()->create(['role' => 'admin']);

    $store = app(CreateStore::class)(
        "{$prefix} Store", $adminUser, '9998887777', 'Test City', 1000000, 50000, adminOperator(),
    );

    return ['admin' => $adminUser, 'store' => $store];
}

beforeEach(function () {
    $this->seed();
});

test('a member cannot access any admin portal route', function () {
    $member = adminPortalMember('AP-MEMBER');

    $this->actingAs($member->user)->get('/admin/sales')->assertForbidden();
});

test('an admin user with no assigned store is forbidden from every admin portal route', function () {
    $adminUser = User::factory()->create(['role' => 'admin']);

    $this->actingAs($adminUser)->get('/admin/sales')->assertForbidden();
});

test('an admin with an assigned store sees the real A01 Store Dashboard at the shared /dashboard route', function () {
    $result = makeAdminWithStore('DASH');

    $response = $this->actingAs($result['admin'])->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/dashboard')
        ->where('store.name', 'DASH Store')
        ->where('wallet_balance', '50000.00'));
});

test('an admin can update only contact/location on their store profile', function () {
    $result = makeAdminWithStore('PROFILE');

    $this->actingAs($result['admin'])
        ->post('/admin/profile', ['contact' => '9990001111', 'location' => 'New Location'])
        ->assertRedirect('/admin/profile');

    $fresh = $result['store']->fresh();
    expect($fresh->contact)->toBe('9990001111');
    expect($fresh->location)->toBe('New Location');
});

test('an admin can record a new sale, generating an invoice and an activity log entry', function () {
    $result = makeAdminWithStore('SALE');
    $store = $result['store'];

    $this->actingAs($result['admin'])
        ->post('/admin/sales', [
            'transaction_type' => 'new_sale',
            'item_name' => 'Gold Ring',
            'item_weight' => 5,
            'quantity' => 1,
            'rate' => 6000,
            'sale_amount' => 30000,
            'gst_amount' => 0,
            'payment_source' => 'cash',
        ])
        ->assertRedirect('/admin/sales');

    $sale = StoreSale::where('store_id', $store->id)->firstOrFail();
    expect($sale->item_name)->toBe('Gold Ring');
    expect($sale->invoice)->not->toBeNull();
    expect(StoreActivityLog::where('store_id', $store->id)->where('action_type', 'store_sale_new_sale')->exists())->toBeTrue();
});

test('an admin can record a sale against tracked inventory, decrementing stock', function () {
    $result = makeAdminWithStore('STOCK');
    $store = $result['store'];
    $item = app(AllocateStoreInventoryItem::class)($store, 'Silver Chain', 'silver', 20, 5, 2000, adminOperator());

    $this->actingAs($result['admin'])
        ->post('/admin/sales', [
            'transaction_type' => 'new_sale',
            'store_inventory_item_id' => $item->id,
            'quantity' => 2,
            'sale_amount' => 4000,
            'gst_amount' => 0,
            'payment_source' => 'store_wallet',
        ])
        ->assertRedirect('/admin/sales');

    expect($item->fresh()->quantity)->toBe(3);
    expect((float) $store->wallet->fresh()->balance)->toBe(46000.0); // 50,000 advance - 4,000.
});

test('an admin can record an Item Buyback for a member by Customer ID', function () {
    $result = makeAdminWithStore('BUYBACK');
    $store = $result['store'];
    $member = adminPortalMember('AP-SELLER');
    MetalRate::create(['metal' => 'gold', 'rate_per_gram' => 6000, 'effective_from' => now()->toDateString(), 'created_by' => adminOperator()->id]);

    $this->actingAs($result['admin'])
        ->post('/admin/sales/buyback', [
            'customer_id' => $member->customer_id,
            'item_name' => 'Old Ring',
            'metal' => 'gold',
            'weight' => 5,
            'quantity' => 1,
        ])
        ->assertRedirect('/admin/sales');

    $buyback = $store->buybacks()->firstOrFail();
    expect((float) $buyback->price_paid)->toBe(18000.0); // 5g * 6000 * 60%.
    expect($buyback->member_id)->toBe($member->id);
});

test('an admin can record a Plan Jewellery Delivery for a new joining', function () {
    $result = makeAdminWithStore('DELIVERY');
    $store = $result['store'];
    $member = adminPortalMember('AP-NEWJOIN');
    $plan = MembershipPlan::where('code', 'C')->firstOrFail();
    $benefit = ProductBenefit::create([
        'member_id' => $member->id,
        'membership_plan_id' => $plan->id,
        'metal' => 'gold',
        'rate_per_gram_at_entry' => 6000,
        'entry_date' => now()->toDateString(),
    ]);

    $this->actingAs($result['admin'])
        ->post('/admin/sales/delivery', [
            'customer_id' => $member->customer_id,
            'sale_amount' => 30000,
            'gst_amount' => 0,
        ])
        ->assertRedirect('/admin/sales');

    expect($benefit->fresh()->delivered_at)->not->toBeNull();
    expect($benefit->fresh()->store_id)->toBe($store->id);
});

test('an admin can view Inventory, Store Transactions, and Store Reports pages', function () {
    $result = makeAdminWithStore('NAV');
    $actingAs = $this->actingAs($result['admin']);

    $actingAs->get('/admin/inventory')->assertOk();
    $actingAs->get('/admin/transactions')->assertOk();
    $actingAs->get('/admin/reports')->assertOk();
});

test('an admin can download their own store sales report as CSV', function () {
    $result = makeAdminWithStore('REPORT');

    $response = $this->actingAs($result['admin'])->get('/admin/reports/sales/export');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

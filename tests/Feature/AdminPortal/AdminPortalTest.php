<?php

use App\Actions\Store\AllocateStoreInventoryItem;
use App\Actions\Store\CreateStore;
use App\Models\CompanyWallet;
use App\Models\EmiInstallment;
use App\Models\EmiSchedule;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\MetalRate;
use App\Models\Payment;
use App\Models\ProductBenefit;
use App\Models\RuleValue;
use App\Models\Store;
use App\Models\StoreActivityLog;
use App\Models\StoreInventoryItem;
use App\Models\StoreRestockShipment;
use App\Models\StoreSale;
use App\Models\User;
use App\Services\StoreWalletService;
use Illuminate\Support\Str;

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

/** Two 5 g gold rings in a store's stock (T-168 — plan jewellery is handed over from inventory). */
function apGoldStock(Store $store): StoreInventoryItem
{
    return app(AllocateStoreInventoryItem::class)($store, 'Gold Ring', 'gold', 5, 2, 30000, adminOperator());
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

test('an admin can record a purchase (walk-in, no Customer ID), generating an invoice and an activity log entry', function () {
    $result = makeAdminWithStore('SALE');
    $store = $result['store'];

    $this->actingAs($result['admin'])
        ->post('/admin/sales', [
            'transaction_type' => 'purchase',
            'item_name' => 'Gold Ring',
            'metal' => 'gold',
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
    expect($sale->member_id)->toBeNull();
    // T-171 — no bill until someone generates it.
    expect($sale->invoice)->toBeNull();
    expect(StoreActivityLog::where('store_id', $store->id)->where('action_type', 'store_sale_purchase')->exists())->toBeTrue();

    $this->actingAs($result['admin'])->post("/admin/sales/{$sale->id}/bill")->assertRedirect("/admin/sales/{$sale->id}/invoice");
    expect($sale->fresh()->invoice)->not->toBeNull();
    expect(StoreActivityLog::where('store_id', $store->id)->where('action_type', 'invoice_generated')->exists())->toBeTrue();
});

test('a repurchase without a resolvable Customer ID is rejected (T-149 follow-up, 23-09-2026)', function () {
    $result = makeAdminWithStore('NOREPURCHASE');

    $this->actingAs($result['admin'])
        ->post('/admin/sales', [
            'transaction_type' => 'repurchase',
            'item_name' => 'Gold Ring',
            'metal' => 'gold',
            'item_weight' => 5,
            'quantity' => 1,
            'sale_amount' => 30000,
            'gst_amount' => 0,
            'payment_source' => 'cash',
        ])
        ->assertSessionHasErrors('customer_id');
});

test('an admin can record a sale against tracked inventory, decrementing stock', function () {
    $result = makeAdminWithStore('STOCK');
    $store = $result['store'];
    $item = app(AllocateStoreInventoryItem::class)($store, 'Silver Chain', 'silver', 20, 5, 2000, adminOperator());

    $this->actingAs($result['admin'])
        ->post('/admin/sales', [
            'transaction_type' => 'purchase',
            'store_inventory_item_id' => $item->id,
            'quantity' => 2,
            'sale_amount' => 4000,
            'gst_amount' => 0,
            'payment_source' => 'cash',
        ])
        ->assertRedirect('/admin/sales');

    expect($item->fresh()->quantity)->toBe(3);
    expect((float) $store->wallet->fresh()->balance)->toBe(50000.0); // A sale never touches the Store Wallet (T-161).
});

test('a store can open its own sale\'s printable invoice, but not another store\'s (T-160)', function () {
    $result = makeAdminWithStore('INVOICE');
    $item = app(AllocateStoreInventoryItem::class)($result['store'], 'Gold Ring', 'gold', 5, 2, 30000, adminOperator());

    $this->actingAs($result['admin'])
        ->post('/admin/sales', [
            'transaction_type' => 'purchase',
            'store_inventory_item_id' => $item->id,
            'quantity' => 1,
            'payment_source' => 'cash',
        ])
        ->assertRedirect('/admin/sales');

    $sale = StoreSale::where('store_id', $result['store']->id)->firstOrFail();

    // T-171 — no bill yet → 404; generating it opens the original, later views are duplicates.
    $this->actingAs($result['admin'])->get("/admin/sales/{$sale->id}/invoice")->assertNotFound();
    $this->actingAs($result['admin'])
        ->followingRedirects()
        ->post("/admin/sales/{$sale->id}/bill")
        ->assertInertia(fn ($page) => $page->where('invoice.copy', 'original'));

    $this->actingAs($result['admin'])
        ->get("/admin/sales/{$sale->id}/invoice")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('invoices/show')
            ->where('invoice.copy', 'duplicate')
            ->where('invoice.invoice_no', $sale->fresh()->invoice->invoice_no)
            ->where('invoice.seller.name', 'INVOICE Store')
            ->where('invoice.customer', null)
            ->where('invoice.item_name', 'Gold Ring')
            // 5 g × seeded ₹6,000/g, no making, GST 0% (seed defaults) — priced by the server (T-169).
            ->where('invoice.total_invoice_amount', '30000.00'));

    $other = makeAdminWithStore('OTHERINV');
    $this->actingAs($other['admin'])->get("/admin/sales/{$sale->id}/invoice")->assertNotFound();

    // T-171 — Super Admin can open (print / share) the store's bill too.
    $this->actingAs(adminOperator())
        ->get("/super-admin/store-sales/{$sale->id}/invoice")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('invoice.seller.name', 'INVOICE Store'));
});

test('a store sale is priced by the server at the current rate with making and GST, and income uses the metal value only (T-169, TEST.md scenario 31)', function () {
    MetalRate::create(['metal' => 'gold', 'rate_per_gram' => 6000, 'making_charge_percent' => 12, 'effective_from' => now()->toDateString(), 'created_by' => adminOperator()->id]);
    RuleValue::where('key', 'store_gst_percent')->update(['value' => 3]);
    $result = makeAdminWithStore('PRICED');
    // Store Profit Distribution needs the Store Owner to be a network member (§16.4).
    Member::create(['user_id' => $result['admin']->id, 'customer_id' => 'AP-PRICED-OWNER', 'status' => 'active', 'activated_at' => now()]);
    $item = app(AllocateStoreInventoryItem::class)($result['store'], 'Gold Ring', 'gold', 5, 3, 30000, adminOperator());

    // Anything typed for rate / amount / GST is ignored.
    $this->actingAs($result['admin'])
        ->post('/admin/sales', [
            'transaction_type' => 'purchase',
            'store_inventory_item_id' => $item->id,
            'quantity' => 1,
            'rate' => 1,
            'sale_amount' => 1,
            'gst_amount' => 0,
            'payment_source' => 'cash',
        ])
        ->assertRedirect('/admin/sales');

    $sale = StoreSale::where('store_id', $result['store']->id)->firstOrFail();
    expect((float) $sale->rate)->toBe(6000.0)
        ->and((float) $sale->metal_value)->toBe(30000.0)
        ->and((float) $sale->making_charge_percent)->toBe(12.0)
        ->and((float) $sale->making_charges)->toBe(3600.0)
        ->and((float) $sale->sale_amount)->toBe(33600.0)
        ->and((float) $sale->gst_percent)->toBe(3.0)
        ->and((float) $sale->gst_amount)->toBe(1008.0)
        ->and((float) $sale->total_invoice_amount)->toBe(34608.0);

    // Store Owner's 2% is on the metal value only: 2% of 30,000.
    expect((float) $sale->profitDistributions()->where('beneficiary_type', 'store_owner')->value('amount'))->toBe(600.0);

    // Quantity 2 doubles every figure; a manual item needs its weight.
    $this->actingAs($result['admin'])
        ->post('/admin/sales', ['transaction_type' => 'purchase', 'store_inventory_item_id' => $item->id, 'quantity' => 2, 'payment_source' => 'cash']);
    expect((float) StoreSale::where('store_id', $result['store']->id)->latest('id')->value('total_invoice_amount'))->toBe(69216.0);

    $this->actingAs($result['admin'])
        ->post('/admin/sales', ['transaction_type' => 'purchase', 'item_name' => 'Loose chain', 'metal' => 'gold', 'quantity' => 1, 'payment_source' => 'cash'])
        ->assertSessionHasErrors('item_weight');
});

test('the Store Wallet is no longer accepted as a sale payment source (T-161)', function () {
    $result = makeAdminWithStore('NOWALLET');
    $item = app(AllocateStoreInventoryItem::class)($result['store'], 'Silver Chain', 'silver', 20, 5, 2000, adminOperator());

    $this->actingAs($result['admin'])
        ->post('/admin/sales', [
            'transaction_type' => 'purchase',
            'store_inventory_item_id' => $item->id,
            'quantity' => 1,
            'sale_amount' => 2000,
            'gst_amount' => 0,
            'payment_source' => 'store_wallet',
        ])
        ->assertSessionHasErrors('payment_source');

    expect($item->fresh()->quantity)->toBe(5);
    expect((float) $result['store']->wallet->fresh()->balance)->toBe(50000.0);
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
    expect($buyback->walk_in_name)->toBeNull();
});

test('an admin can record an Item Buyback for a non-member walk-in (T-149 follow-up, 23-09-2026)', function () {
    $result = makeAdminWithStore('WALKINBUYBACK');
    $store = $result['store'];
    MetalRate::create(['metal' => 'gold', 'rate_per_gram' => 6000, 'effective_from' => now()->toDateString(), 'created_by' => adminOperator()->id]);

    $this->actingAs($result['admin'])
        ->post('/admin/sales/buyback', [
            'walk_in_name' => 'Ramesh Kumar',
            'walk_in_mobile' => '9998887771',
            'item_name' => 'Old Bangle',
            'metal' => 'gold',
            'weight' => 5,
            'quantity' => 1,
        ])
        ->assertRedirect('/admin/sales');

    $buyback = $store->buybacks()->firstOrFail();
    expect($buyback->member_id)->toBeNull();
    expect($buyback->walk_in_name)->toBe('Ramesh Kumar');
    expect($buyback->walk_in_mobile)->toBe('9998887771');
});

test('an Item Buyback needs either a Customer ID or a walk-in name', function () {
    $result = makeAdminWithStore('NOBUYBACKID');
    MetalRate::create(['metal' => 'gold', 'rate_per_gram' => 6000, 'effective_from' => now()->toDateString(), 'created_by' => adminOperator()->id]);

    $this->actingAs($result['admin'])
        ->post('/admin/sales/buyback', [
            'item_name' => 'Old Bangle',
            'metal' => 'gold',
            'weight' => 5,
            'quantity' => 1,
        ])
        ->assertSessionHasErrors('walk_in_name');
});

// ---------------------------------------------------------------- T-152: Jewellery Restock Shipments

test('a Plan Jewellery Delivery whose registration was paid online owes the delivering store a restock (T-152, DOMAIN_LOGIC.md §16.12)', function () {
    $result = makeAdminWithStore('RESTOCK1');
    $store = $result['store'];
    $member = adminPortalMember('AP-ONLINE1');
    Payment::create([
        'member_id' => $member->id,
        'type' => 'registration',
        'amount' => 30000,
        'mode' => 'online',
        'status' => 'paid',
        'idempotency_key' => (string) Str::uuid(),
        'paid_at' => now(),
    ]);
    $plan = MembershipPlan::where('code', 'C')->firstOrFail();
    $benefit = ProductBenefit::create([
        'member_id' => $member->id,
        'membership_plan_id' => $plan->id,
        'metal' => 'gold',
        'rate_per_gram_at_entry' => 6000,
        'entry_date' => now()->toDateString(),
    ]);

    $this->actingAs($result['admin'])
        ->post('/admin/sales/delivery', ['customer_id' => $member->customer_id, 'store_inventory_item_id' => apGoldStock($store)->id])
        ->assertRedirect('/admin/sales');

    $shipment = StoreRestockShipment::where('product_benefit_id', $benefit->id)->firstOrFail();
    expect($shipment->store_id)->toBe($store->id);
    expect($shipment->status)->toBe('owed');
    expect((float) $shipment->value)->toBe(30000.0);
});

test('a Plan Jewellery Delivery whose registration was settled by the SAME delivering store owes no restock', function () {
    $result = makeAdminWithStore('RESTOCK2');
    $store = $result['store'];
    $member = adminPortalMember('AP-PAID1');
    Payment::create([
        'member_id' => $member->id,
        'paying_store_id' => $store->id,
        'type' => 'registration',
        'amount' => 30000,
        'mode' => 'cash',
        'status' => 'paid',
        'idempotency_key' => (string) Str::uuid(),
        'paid_at' => now(),
    ]);
    $plan = MembershipPlan::where('code', 'C')->firstOrFail();
    $benefit = ProductBenefit::create([
        'member_id' => $member->id,
        'membership_plan_id' => $plan->id,
        'metal' => 'gold',
        'rate_per_gram_at_entry' => 6000,
        'entry_date' => now()->toDateString(),
    ]);

    $this->actingAs($result['admin'])
        ->post('/admin/sales/delivery', ['customer_id' => $member->customer_id, 'store_inventory_item_id' => apGoldStock($store)->id])
        ->assertRedirect('/admin/sales');

    expect(StoreRestockShipment::where('product_benefit_id', $benefit->id)->exists())->toBeFalse();
});

test('a restock shipment goes owed -> sent (Super Admin) -> received (store), landing in the store\'s tracked inventory', function () {
    $result = makeAdminWithStore('RESTOCK3');
    $store = $result['store'];
    $member = adminPortalMember('AP-ONLINE2');
    Payment::create([
        'member_id' => $member->id,
        'type' => 'registration',
        'amount' => 35000,
        'mode' => 'online',
        'status' => 'paid',
        'idempotency_key' => (string) Str::uuid(),
        'paid_at' => now(),
    ]);
    $plan = MembershipPlan::where('code', 'C')->firstOrFail();
    ProductBenefit::create([
        'member_id' => $member->id,
        'membership_plan_id' => $plan->id,
        'metal' => 'gold',
        'rate_per_gram_at_entry' => 7000,
        'entry_date' => now()->toDateString(),
    ]);
    $this->actingAs($result['admin'])->post('/admin/sales/delivery', ['customer_id' => $member->customer_id, 'store_inventory_item_id' => apGoldStock($store)->id]);
    $shipment = StoreRestockShipment::where('store_id', $store->id)->firstOrFail();

    // A different store must not be able to touch this one.
    $other = makeAdminWithStore('RESTOCKOTHER');
    $this->actingAs($other['admin'])->post("/admin/inventory/restock/{$shipment->id}/received")->assertForbidden();

    // Not sent yet — the store cannot confirm received before Super Admin ships it.
    $this->actingAs($result['admin'])->post("/admin/inventory/restock/{$shipment->id}/received")->assertSessionHasErrors('shipment');

    $this->actingAs(adminOperator())
        ->post("/super-admin/restock-shipments/{$shipment->id}/send")
        ->assertRedirect();
    expect($shipment->fresh()->status)->toBe('sent');
    expect($shipment->fresh()->sent_by)->toBe(adminOperator()->id);

    $this->actingAs($result['admin'])
        ->post("/admin/inventory/restock/{$shipment->id}/received")
        ->assertRedirect('/admin/inventory');

    $fresh = $shipment->fresh();
    expect($fresh->status)->toBe('received');
    expect($fresh->receivedBy->id)->toBe($result['admin']->id);
    $item = $fresh->resultingInventoryItem;
    expect($item)->not->toBeNull();
    expect($item->store_id)->toBe($store->id);
    // T-168 — the shipment is worth the delivered piece's metal value: 5 g × seeded ₹6,000/g.
    expect((float) $item->price)->toBe(30000.0);
    expect($item->quantity)->toBeGreaterThanOrEqual(1);
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

    $stock = apGoldStock($store);
    $silver = app(AllocateStoreInventoryItem::class)($store, 'Silver Anklet', 'silver', 50, 1, 17500, adminOperator());

    // A gold plan cannot be handed over as a silver piece, and nothing is marked delivered.
    $this->actingAs($result['admin'])
        ->post('/admin/sales/delivery', ['customer_id' => $member->customer_id, 'store_inventory_item_id' => $silver->id])
        ->assertSessionHasErrors('store_inventory_item_id');
    expect($benefit->fresh()->delivered_at)->toBeNull();

    $this->actingAs($result['admin'])
        ->post('/admin/sales/delivery', [
            'customer_id' => $member->customer_id,
            'store_inventory_item_id' => $stock->id,
        ])
        ->assertRedirect('/admin/sales');

    expect($benefit->fresh()->delivered_at)->not->toBeNull();
    expect($benefit->fresh()->store_id)->toBe($store->id);
    // T-168 — the piece came out of stock and the sale is priced like a purchase (5 g × ₹6,000).
    expect($stock->fresh()->quantity)->toBe(1);
    $sale = StoreSale::where('store_id', $store->id)->where('transaction_type', 'new_sale')->firstOrFail();
    expect($sale->item_name)->toBe('Gold Ring')
        ->and((float) $sale->metal_value)->toBe(30000.0)
        ->and($sale->store_inventory_item_id)->toBe($stock->id);
});

test('a Current Rate member\'s delivery is priced at the rate locked when they booked, not today\'s (T-168)', function () {
    $result = makeAdminWithStore('LOCKED');
    $member = adminPortalMember('AP-LOCKED');
    $plan = MembershipPlan::where('code', 'C')->firstOrFail();
    EmiSchedule::create([
        'member_id' => $member->id, 'membership_plan_id' => $plan->id, 'total_installments' => 10,
        'rate_booking_method' => 'current_rate', 'installment_amount' => 5000, 'rate_per_gram_at_booking' => 5000, 'fixed_weight_grams' => 5,
    ]);
    ProductBenefit::create(['member_id' => $member->id, 'membership_plan_id' => $plan->id, 'metal' => 'gold', 'entry_date' => now()->toDateString()]);

    $this->actingAs($result['admin'])
        ->post('/admin/sales/delivery', ['customer_id' => $member->customer_id, 'store_inventory_item_id' => apGoldStock($result['store'])->id])
        ->assertRedirect('/admin/sales');

    // 5 g × the locked ₹5,000/g, although today's seeded rate is ₹6,000/g.
    $sale = StoreSale::where('store_id', $result['store']->id)->firstOrFail();
    expect((float) $sale->rate)->toBe(5000.0)
        ->and((float) $sale->metal_value)->toBe(25000.0);
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

// ---------------------------------------------------------------- T-151: Store-Wallet-Funded Cash Collection

test('a Store Admin can collect a member\'s pending cash EMI installment payment via the Store Wallet (T-151, DOMAIN_LOGIC.md §12.2(a))', function () {
    $result = makeAdminWithStore('COLLECT');
    $store = $result['store'];
    $balanceBefore = (float) $store->wallet->fresh()->balance;

    $memberUser = User::factory()->create(['role' => 'member']);
    $plan = MembershipPlan::where('code', 'A')->firstOrFail();
    $member = Member::create([
        'user_id' => $memberUser->id,
        'customer_id' => 'COLLECT1',
        'membership_plan_id' => $plan->id,
        'status' => 'active',
        'activated_at' => now(),
    ]);
    $schedule = EmiSchedule::create([
        'member_id' => $member->id,
        'membership_plan_id' => $plan->id,
        'total_installments' => 20,
        'rate_booking_method' => 'future_rate',
        'installment_amount' => 1000,
    ]);
    $installment = EmiInstallment::create([
        'emi_schedule_id' => $schedule->id,
        'installment_no' => 2,
        'due_date' => now()->toDateString(),
        'amount' => 1000,
        'status' => 'due',
    ]);

    $this->actingAs($memberUser)->post('/member/emi/'.$installment->id.'/pay', ['mode' => 'cash'])->assertRedirect();

    $payment = Payment::where('member_id', $member->id)->where('type', 'emi_installment')->firstOrFail();
    expect($payment->status)->toBe('pending');

    // The Store Admin finds the member's pending payment via the search on the Sales page.
    $search = $this->actingAs($result['admin'])->get('/admin/sales?collect_customer_id=COLLECT1');
    $search->assertOk();
    $search->assertInertia(fn ($page) => $page
        ->where('collect_search.found', true)
        ->where('collect_search.payments.0.id', $payment->id));

    $this->actingAs($result['admin'])
        ->post("/admin/sales/collect-payment/{$payment->id}")
        ->assertRedirect();

    expect($payment->fresh()->status)->toBe('paid');
    expect($payment->fresh()->mode)->toBe('cash');
    expect($payment->fresh()->paying_store_id)->toBe($store->id);
    expect($installment->fresh()->status)->toBe('paid');
    expect((float) $store->wallet->fresh()->balance)->toBe($balanceBefore - 1000.0);
});

test('collecting a payment via Store Wallet is blocked when the store\'s wallet balance is insufficient', function () {
    $result = makeAdminWithStore('POORSTORE');
    $store = $result['store'];
    // Drain the store's own advance so the wallet balance is 0.
    app(StoreWalletService::class)->deduct($store->wallet->fresh(), (float) $store->wallet->fresh()->balance, adminOperator(), 'drain for test');

    $memberUser = User::factory()->create(['role' => 'member']);
    $plan = MembershipPlan::where('code', 'A')->firstOrFail();
    $member = Member::create([
        'user_id' => $memberUser->id,
        'customer_id' => 'POORSTORE1',
        'membership_plan_id' => $plan->id,
        'status' => 'active',
        'activated_at' => now(),
    ]);
    $schedule = EmiSchedule::create([
        'member_id' => $member->id,
        'membership_plan_id' => $plan->id,
        'total_installments' => 20,
        'rate_booking_method' => 'future_rate',
        'installment_amount' => 1000,
    ]);
    $installment = EmiInstallment::create([
        'emi_schedule_id' => $schedule->id,
        'installment_no' => 2,
        'due_date' => now()->toDateString(),
        'amount' => 1000,
        'status' => 'due',
    ]);
    $this->actingAs($memberUser)->post('/member/emi/'.$installment->id.'/pay', ['mode' => 'cash']);
    $payment = Payment::where('member_id', $member->id)->where('type', 'emi_installment')->firstOrFail();

    $this->actingAs($result['admin'])
        ->post("/admin/sales/collect-payment/{$payment->id}")
        ->assertSessionHasErrors('amount');

    expect($payment->fresh()->status)->toBe('pending');
});

test('a Store Admin can collect a member\'s pending cash registration payment via the Store Wallet, activating the member (T-151)', function () {
    $result = makeAdminWithStore('COLLECTREG');
    $store = $result['store'];
    $sponsor = adminPortalMember('COLLECTSP');
    $sponsor->update(['status' => 'active']);
    $plan = MembershipPlan::where('code', 'F')->firstOrFail();

    $this->post('/join', [
        'sponsor_code' => $sponsor->customer_id,
        'placement_side' => 'left',
        'gender' => 'female',
        'name' => 'Collect Reg Member',
        'email' => 'collectreg@example.test',
        'mobile' => '9876500001',
        'membership_plan_id' => $plan->id,
        'payment_mode' => 'cash',
    ])->assertRedirect();

    $newMember = Member::where('sponsor_id', $sponsor->id)->firstOrFail();
    $payment = Payment::where('member_id', $newMember->id)->where('type', 'registration')->firstOrFail();
    expect($payment->status)->toBe('pending');
    expect($newMember->customer_id)->toBeNull();

    $this->actingAs($result['admin'])
        ->post("/admin/sales/collect-payment/{$payment->id}")
        ->assertRedirect();

    expect($payment->fresh()->status)->toBe('paid');
    expect($payment->fresh()->paying_store_id)->toBe($store->id);
    expect($newMember->fresh()->status)->toBe('active');
    expect($newMember->fresh()->customer_id)->not->toBeNull();
});

// ---------------------------------------------------------------- T-153: Assisted Registration (Store)

test('a Store can register a different, new member and pay from the Store Wallet (T-153, DOMAIN_LOGIC.md §12.2(b))', function () {
    $result = makeAdminWithStore('ARSTORE');
    $store = $result['store'];
    $sponsor = adminPortalMember('AR-STORESPONSOR');
    $sponsor->update(['status' => 'active']);
    $plan = MembershipPlan::where('code', 'F')->firstOrFail();
    $balanceBefore = (float) $store->wallet->fresh()->balance;

    $this->actingAs($result['admin'])
        ->get('/admin/register-new')
        ->assertInertia(fn ($page) => $page->where('wallet_balance', fn ($value) => (float) $value === $balanceBefore));

    $this->actingAs($result['admin'])
        ->post('/admin/register-new', [
            'sponsor_code' => $sponsor->customer_id,
            'placement_side' => 'left',
            'gender' => 'male',
            'name' => 'Store Assisted Member',
            'email' => 'store-assisted@example.test',
            'mobile' => '9876544444',
            'membership_plan_id' => $plan->id,
            'payment_mode' => 'wallet',
        ])
        ->assertRedirect();

    $newMember = Member::where('sponsor_id', $sponsor->id)->firstOrFail();
    expect($newMember->status)->toBe('active');

    $payment = $newMember->payments->firstWhere('type', 'registration');
    expect($payment->status)->toBe('paid');
    expect($payment->mode)->toBe('wallet');
    expect($payment->paying_store_id)->toBe($store->id);
    expect($payment->paying_member_id)->toBeNull();

    expect((float) $store->wallet->fresh()->balance)->toBe($balanceBefore - 50000.0);

    $companyWallet = CompanyWallet::firstOrFail();
    expect((float) $companyWallet->balance)->toBe(50000.0);
});

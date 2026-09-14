<?php

use App\Actions\Compensation\CalculatePurchaseRepurchaseIncome;
use App\Actions\Compensation\CalculateStoreProfitDistribution;
use App\Actions\Store\AllocateStoreInventoryItem;
use App\Actions\Store\ConfirmStoreSale;
use App\Actions\Store\CreateStore;
use App\Actions\Store\RecordItemBuyback;
use App\Actions\Store\RecordPlanJewelleryDelivery;
use App\Actions\Store\RecordStoreWalletTopup;
use App\Models\IncomeLedgerCalculation;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\MetalRate;
use App\Models\ProductBenefit;
use App\Models\Store;
use App\Models\StoreActivityLog;
use App\Models\StoreProfitDistribution;
use App\Models\StoreSale;
use App\Models\User;
use App\Services\StoreWalletService;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §15/§16 (Store Management, Store Wallet, Purchase/
 * Repurchase Upline Income, Invoice, Store Profit Distribution, Inventory,
 * Item Buyback), Docs/TEST.md scenarios 4/5/15/16/17.
 */
function storeMember(string $customerId, ?Member $sponsor = null): Member
{
    $user = User::factory()->create(['role' => 'member']);

    return Member::create([
        'user_id' => $user->id,
        'customer_id' => $customerId,
        'sponsor_id' => $sponsor?->id,
        'status' => 'active',
        'activated_at' => now(),
    ]);
}

/** Same ordering convention as CalculateLevelIncome's tests: index 0 = Level 1 (direct sponsor). */
function buildStoreSponsorChain(string $prefix, int $count): array
{
    $chain = [];
    $sponsor = null;

    for ($i = 1; $i <= $count; $i++) {
        $sponsor = storeMember("{$prefix}{$i}", $sponsor);
        $chain[] = $sponsor;
    }

    return array_reverse($chain);
}

/** @return array{store: Store, owner: Member} */
function makeStoreWithOwnerChain(string $prefix, int $sponsorLevels = 0): array
{
    $chain = $sponsorLevels > 0 ? buildStoreSponsorChain($prefix.'SPON', $sponsorLevels) : [];
    $owner = storeMember("{$prefix}OWNER", $chain[0] ?? null);

    $store = app(CreateStore::class)(
        "{$prefix} Store",
        $owner->user,
        '9999999999',
        'Test City',
        1000000,
        0,
        User::where('role', 'super_admin')->firstOrFail(),
    );

    return ['store' => $store, 'owner' => $owner, 'chain' => $chain];
}

function makeGoldRate(float $ratePerGram): MetalRate
{
    return MetalRate::create([
        'metal' => 'gold',
        'rate_per_gram' => $ratePerGram,
        'effective_from' => now()->toDateString(),
        'created_by' => User::where('role', 'super_admin')->firstOrFail()->id,
    ]);
}

beforeEach(function () {
    $this->seed();
});

test('CreateStore opens a wallet and credits the initial advance amount', function () {
    $owner = storeMember('CRST-OWNER');
    $store = app(CreateStore::class)(
        'Test Store', $owner->user, '9998887777', 'Delhi', 1500000, 250000,
        User::where('role', 'super_admin')->firstOrFail(),
    );

    expect((float) $store->wallet->balance)->toBe(250000.0);
    expect($store->ownerMember()->id)->toBe($owner->id);
});

test('RecordStoreWalletTopup credits the wallet, and a deduction beyond balance is blocked', function () {
    $result = makeStoreWithOwnerChain('TOPUP');
    $wallet = $result['store']->wallet;
    $operator = User::where('role', 'super_admin')->firstOrFail();

    app(RecordStoreWalletTopup::class)($wallet, 5000, $operator);
    expect((float) $wallet->fresh()->balance)->toBe(5000.0);

    expect(fn () => app(StoreWalletService::class)->deduct($wallet->fresh(), 10000, $operator, 'over-limit'))
        ->toThrow(ValidationException::class);
    expect((float) $wallet->fresh()->balance)->toBe(5000.0);
});

test('a confirmed sale decrements tracked inventory atomically, and insufficient stock blocks the sale', function () {
    $result = makeStoreWithOwnerChain('INV');
    $store = $result['store'];
    $operator = User::where('role', 'super_admin')->firstOrFail();

    $item = app(AllocateStoreInventoryItem::class)($store, '22K Gold Ring', 'gold', 5.000, 10, 35000, $operator);

    $sale = app(ConfirmStoreSale::class)(
        store: $store, member: null, transactionType: 'new_sale', itemName: $item->item_name,
        inventoryItem: $item, itemWeight: 5.000, quantity: 3, rate: 35000,
        saleAmount: 105000, gstAmount: 0, paymentSource: 'cash', operator: $operator,
    );

    expect($item->fresh()->quantity)->toBe(7);
    expect($sale->quantity)->toBe(3);
    expect($sale->invoice)->not->toBeNull();

    expect(fn () => app(ConfirmStoreSale::class)(
        store: $store, member: null, transactionType: 'new_sale', itemName: $item->item_name,
        inventoryItem: $item->fresh(), itemWeight: 5.000, quantity: 10, rate: 35000,
        saleAmount: 350000, gstAmount: 0, paymentSource: 'cash', operator: $operator,
    ))->toThrow(ValidationException::class);

    expect($item->fresh()->quantity)->toBe(7);
    expect(StoreSale::count())->toBe(1);
});

test('a store-initiated sale paid from the Store Wallet deducts atomically with the sale', function () {
    $result = makeStoreWithOwnerChain('SWPAY');
    $store = $result['store'];
    $operator = User::where('role', 'super_admin')->firstOrFail();
    app(RecordStoreWalletTopup::class)($store->wallet, 20000, $operator);

    $sale = app(ConfirmStoreSale::class)(
        store: $store, member: null, transactionType: 'new_sale', itemName: 'Silver Coin',
        inventoryItem: null, itemWeight: 10, quantity: 1, rate: 1500,
        saleAmount: 15000, gstAmount: 0, paymentSource: 'store_wallet', operator: $operator,
    );

    expect((float) $store->wallet->fresh()->balance)->toBe(5000.0);
    expect($sale->storeWalletDeduction)->not->toBeNull();
});

test('Item Buyback prices on the current market rate, restocks inventory, and never triggers compensation', function () {
    $result = makeStoreWithOwnerChain('BUYBACK', 3);
    $store = $result['store'];
    $member = storeMember('BUYBACK-SELLER');
    $rate = makeGoldRate(6000);
    $operator = User::where('role', 'super_admin')->firstOrFail();

    $buyback = app(RecordItemBuyback::class)($store, $member, '22K Gold Chain', 'gold', 10.000, 1, 'old chain', $rate, $operator);

    expect((float) $buyback->rate_per_gram_at_buyback)->toBe(6000.0);
    expect((float) $buyback->buyback_percent)->toBe(60.0);
    expect((float) $buyback->price_paid)->toBe(36000.0); // 10g * 6000 * 60%.
    expect($buyback->resultingInventoryItem->quantity)->toBe(1);
    expect((float) $buyback->resultingInventoryItem->weight)->toBe(10.0);

    expect(StoreProfitDistribution::count())->toBe(0);
    expect(IncomeLedgerCalculation::where('type', 'purchase_repurchase')->count())->toBe(0);
    expect((float) $member->fresh()->wallet_balance)->toBe(0.0);
});

test('Purchase/Repurchase Upline Income pays self 2% and the full 12-level Sponsor/Direct chain', function () {
    $chain = buildStoreSponsorChain('PRI', 12);
    $payer = storeMember('PRIPAYER', $chain[0]);
    $store = Store::create(['name' => 'PRI Store', 'status' => 'active']);
    $sale = StoreSale::create([
        'store_id' => $store->id, 'member_id' => $payer->id, 'transaction_type' => 'repurchase',
        'item_name' => 'Gold Chain', 'quantity' => 1, 'sale_amount' => 10000, 'gst_amount' => 0,
        'total_invoice_amount' => 10000, 'payment_source' => 'cash', 'distribution_status' => 'pending',
        'status' => 'confirmed',
    ]);

    app(CalculatePurchaseRepurchaseIncome::class)($sale);

    $rows = IncomeLedgerCalculation::where('source_store_sale_id', $sale->id)
        ->where('type', 'purchase_repurchase')->orderBy('level_no')->get();

    expect($rows)->toHaveCount(13); // self (level_no null) + 12 levels.
    expect((float) $payer->fresh()->wallet_balance)->toBe(200.0); // 2% self.

    $expected = [1 => 100.0, 2 => 50.0, 3 => 50.0, 4 => 50.0, 5 => 50.0, 6 => 50.0, 7 => 25.0, 8 => 25.0, 9 => 25.0, 10 => 25.0, 11 => 25.0, 12 => 25.0];
    foreach ($chain as $index => $beneficiary) {
        expect((float) $beneficiary->fresh()->wallet_balance)->toBe($expected[$index + 1]);
    }
    expect((float) $rows->sum('amount'))->toBe(700.0); // 7% of ₹10,000: 2% self + 5% upline.

    // Idempotent.
    app(CalculatePurchaseRepurchaseIncome::class)($sale);
    expect(IncomeLedgerCalculation::where('source_store_sale_id', $sale->id)->count())->toBe(13);
    expect((float) $payer->fresh()->wallet_balance)->toBe(200.0);
});

test('Purchase/Repurchase Income never fires for a walk-in sale with no purchasing member', function () {
    $store = Store::create(['name' => 'WALKIN Store', 'status' => 'active']);
    $sale = StoreSale::create([
        'store_id' => $store->id, 'member_id' => null, 'transaction_type' => 'new_sale',
        'item_name' => 'Gold Ring', 'quantity' => 1, 'sale_amount' => 10000, 'gst_amount' => 0,
        'total_invoice_amount' => 10000, 'payment_source' => 'cash', 'distribution_status' => 'pending',
        'status' => 'confirmed',
    ]);

    app(CalculatePurchaseRepurchaseIncome::class)($sale);

    expect(IncomeLedgerCalculation::where('source_store_sale_id', $sale->id)->count())->toBe(0);
});

test('Store Profit Distribution pays owner + 3 Sponsor/Direct levels on every store-attributed sale', function () {
    $result = makeStoreWithOwnerChain('SPD', 3);
    $store = $result['store'];
    $ownerChain = $result['chain']; // index 0 = owner's direct sponsor (Level 1).

    $sale = StoreSale::create([
        'store_id' => $store->id, 'member_id' => null, 'transaction_type' => 'new_sale',
        'item_name' => 'Gold Necklace', 'quantity' => 1, 'sale_amount' => 50000, 'gst_amount' => 0,
        'total_invoice_amount' => 50000, 'payment_source' => 'cash', 'distribution_status' => 'pending',
        'status' => 'confirmed',
    ]);

    app(CalculateStoreProfitDistribution::class)($sale);

    $rows = StoreProfitDistribution::where('store_sale_id', $sale->id)->get()->keyBy('beneficiary_type');
    expect($rows)->toHaveCount(4);
    expect((float) $rows['store_owner']->amount)->toBe(1000.0);
    expect((float) $rows['sponsor_level_1']->amount)->toBe(250.0);
    expect((float) $rows['sponsor_level_2']->amount)->toBe(125.0);
    expect((float) $rows['sponsor_level_3']->amount)->toBe(125.0);
    expect((float) $rows->sum('amount'))->toBe(1500.0);

    expect((float) $result['owner']->fresh()->wallet_balance)->toBe(1000.0);
    expect((float) $ownerChain[0]->fresh()->wallet_balance)->toBe(250.0);

    expect($sale->fresh()->distribution_status)->toBe('processed');

    // A walk-in/non-member sale still gets Store Profit Distribution but zero income_ledger_calculations rows.
    expect(IncomeLedgerCalculation::where('source_store_sale_id', $sale->id)->count())->toBe(0);

    // Idempotent.
    app(CalculateStoreProfitDistribution::class)($sale->fresh());
    expect(StoreProfitDistribution::where('store_sale_id', $sale->id)->count())->toBe(4);
    expect((float) $result['owner']->fresh()->wallet_balance)->toBe(1000.0);
});

test('a member purchase fires both Store Profit Distribution and Purchase/Repurchase Income on the same sale', function () {
    $storeResult = makeStoreWithOwnerChain('BOTH', 3);
    $store = $storeResult['store'];
    $purchaserChain = buildStoreSponsorChain('BOTHPUR', 2);
    $purchaser = storeMember('BOTHPURCHASER', $purchaserChain[0]);
    $operator = User::where('role', 'super_admin')->firstOrFail();

    app(ConfirmStoreSale::class)(
        store: $store, member: $purchaser, transactionType: 'new_sale', itemName: 'Gold Bangle',
        inventoryItem: null, itemWeight: null, quantity: 1, rate: null,
        saleAmount: 50000, gstAmount: 0, paymentSource: 'cash', operator: $operator,
    );

    $sale = StoreSale::where('store_id', $store->id)->firstOrFail();
    expect(StoreProfitDistribution::where('store_sale_id', $sale->id)->count())->toBe(4);
    expect(IncomeLedgerCalculation::where('source_store_sale_id', $sale->id)->where('type', 'purchase_repurchase')->count())->toBe(13);
    expect((float) $purchaser->fresh()->wallet_balance)->toBe(1000.0); // 2% self of ₹50,000.
});

test('RecordPlanJewelleryDelivery marks the entitlement delivered, fires Store Profit Distribution, and rejects a second delivery', function () {
    $result = makeStoreWithOwnerChain('DELIV', 3);
    $store = $result['store'];
    $ownerChain = $result['chain'];
    $member = storeMember('DELIV-MEMBER');
    $plan = MembershipPlan::where('code', 'C')->firstOrFail();
    $operator = User::where('role', 'super_admin')->firstOrFail();

    $benefit = ProductBenefit::create([
        'member_id' => $member->id,
        'membership_plan_id' => $plan->id,
        'metal' => 'gold',
        'rate_per_gram_at_entry' => 6000,
        'entry_date' => now()->toDateString(),
    ]);

    $sale = app(RecordPlanJewelleryDelivery::class)($benefit, $store, 30000, 0, $operator);

    expect($benefit->fresh()->delivered_at)->not->toBeNull();
    expect($benefit->fresh()->store_id)->toBe($store->id);
    expect($sale->transaction_type)->toBe('new_sale');
    expect($sale->member_id)->toBe($member->id);

    $rows = StoreProfitDistribution::where('store_sale_id', $sale->id)->get()->keyBy('beneficiary_type');
    expect((float) $rows['store_owner']->amount)->toBe(600.0);
    expect((float) $rows['sponsor_level_1']->amount)->toBe(150.0);
    expect((float) $rows['sponsor_level_2']->amount)->toBe(75.0);
    expect((float) $rows['sponsor_level_3']->amount)->toBe(75.0);
    expect((float) $ownerChain[0]->fresh()->wallet_balance)->toBe(150.0);

    expect(fn () => app(RecordPlanJewelleryDelivery::class)($benefit->fresh(), $store, 30000, 0, $operator))
        ->toThrow(ValidationException::class);
});

test('ConfirmStoreSale generates a printable invoice for every confirmed sale', function () {
    $result = makeStoreWithOwnerChain('INVOICE');
    $operator = User::where('role', 'super_admin')->firstOrFail();

    $sale = app(ConfirmStoreSale::class)(
        store: $result['store'], member: null, transactionType: 'new_sale', itemName: 'Gold Pendant',
        inventoryItem: null, itemWeight: 3, quantity: 1, rate: 6000,
        saleAmount: 18000, gstAmount: 540, paymentSource: 'cash', operator: $operator,
    );

    expect($sale->invoice)->not->toBeNull();
    expect($sale->invoice->invoice_no)->toContain('INV-');
    expect((float) $sale->total_invoice_amount)->toBe(18540.0);
});

test('every Store Action records a Store Activity Log entry (DOMAIN_LOGIC.md §16.3)', function () {
    $result = makeStoreWithOwnerChain('AUDIT');
    $store = $result['store'];
    $operator = User::where('role', 'super_admin')->firstOrFail();

    expect(StoreActivityLog::where('store_id', $store->id)->where('action_type', 'store_created')->exists())->toBeTrue();

    $item = app(AllocateStoreInventoryItem::class)($store, 'Gold Ring', 'gold', 5, 10, 30000, $operator);
    expect(StoreActivityLog::where('action_type', 'inventory_allocated')->where('affected_reference_id', $item->id)->exists())->toBeTrue();

    app(RecordStoreWalletTopup::class)($store->wallet, 5000, $operator);
    expect(StoreActivityLog::where('store_id', $store->id)->where('action_type', 'wallet_topup')->exists())->toBeTrue();

    $sale = app(ConfirmStoreSale::class)(
        store: $store, member: null, transactionType: 'new_sale', itemName: 'Gold Ring',
        inventoryItem: $item, itemWeight: 5, quantity: 1, rate: 6000,
        saleAmount: 30000, gstAmount: 0, paymentSource: 'cash', operator: $operator,
    );
    expect(StoreActivityLog::where('action_type', 'store_sale_new_sale')->where('affected_reference_id', $sale->id)->exists())->toBeTrue();

    $member = storeMember('AUDIT-SELLER');
    $rate = makeGoldRate(6000);
    $buyback = app(RecordItemBuyback::class)($store, $member, 'Old Chain', 'gold', 5, 1, null, $rate, $operator);
    expect(StoreActivityLog::where('action_type', 'item_buyback')->where('affected_reference_id', $buyback->id)->where('affected_member_id', $member->id)->exists())->toBeTrue();
});

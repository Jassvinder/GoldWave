<?php

use App\Actions\Billing\GenerateStoreSaleBill;
use App\Actions\Compensation\CalculatePurchaseRepurchaseIncome;
use App\Actions\Compensation\CalculateStoreProfitDistribution;
use App\Actions\Store\AllocateStoreInventoryItem;
use App\Actions\Store\ConfirmStoreSale;
use App\Actions\Store\CreateStore;
use App\Actions\Store\PriceStoreSale;
use App\Actions\Store\RecordItemBuyback;
use App\Actions\Store\RecordPlanJewelleryDelivery;
use App\Actions\Store\RecordStoreWalletTopup;
use App\Models\IncomeLedgerCalculation;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\MetalRate;
use App\Models\ProductBenefit;
use App\Models\RuleValue;
use App\Models\Store;
use App\Models\StoreActivityLog;
use App\Models\StoreProfitDistribution;
use App\Models\StoreSale;
use App\Models\User;
use App\Services\EarningsVerifier;
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
    expect($sale->invoice)->toBeNull(); // T-171 — bills are generated on request.

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
        metal: 'silver',
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

test('T-110: Item Buyback prices Gold and Silver items using their own separately-configured percentages', function () {
    RuleValue::where('key', 'item_buyback_percent')->update(['value' => 60]);
    RuleValue::where('key', 'item_buyback_percent_gold')->update(['value' => 55]);

    $result = makeStoreWithOwnerChain('BUYBACKSPLIT', 1);
    $store = $result['store'];
    $member = storeMember('BUYBACKSPLIT-SELLER');
    $rate = makeGoldRate(6000);
    $operator = User::where('role', 'super_admin')->firstOrFail();

    $goldBuyback = app(RecordItemBuyback::class)($store, $member, 'Gold Chain', 'gold', 10.000, 1, null, $rate, $operator);
    $silverBuyback = app(RecordItemBuyback::class)($store, $member, 'Silver Chain', 'silver', 10.000, 1, null, $rate, $operator);

    expect((float) $goldBuyback->buyback_percent)->toBe(55.0);
    expect((float) $goldBuyback->price_paid)->toBe(33000.0); // 10g * 6000 * 55%.
    expect((float) $silverBuyback->buyback_percent)->toBe(60.0);
    expect((float) $silverBuyback->price_paid)->toBe(36000.0); // 10g * 6000 * 60%.
});

test('Purchase/Repurchase Upline Income pays self 2% and the full 12-level Sponsor/Direct chain', function () {
    $chain = buildStoreSponsorChain('PRI', 12);
    $payer = storeMember('PRIPAYER', $chain[0]);
    $store = Store::create(['name' => 'PRI Store', 'status' => 'active']);
    $sale = StoreSale::create([
        'store_id' => $store->id, 'member_id' => $payer->id, 'transaction_type' => 'repurchase',
        'item_name' => 'Gold Chain', 'metal' => 'gold', 'quantity' => 1, 'sale_amount' => 10000, 'gst_amount' => 0,
        'total_invoice_amount' => 10000, 'payment_source' => 'cash', 'distribution_status' => 'pending',
        'status' => 'confirmed',
    ]);

    app(CalculatePurchaseRepurchaseIncome::class)($sale);

    $rows = IncomeLedgerCalculation::where('source_store_sale_id', $sale->id)
        ->where('type', 'purchase_repurchase')->orderBy('level_no')->get();

    expect($rows)->toHaveCount(13); // self (level_no null) + 12 levels.
    expect((float) $payer->fresh()->wallet_balance)->toBe(200.0); // 2% self.

    // Gold rates since 29-09-2026: Levels 1-2 1%, 3-6 0.5%, 7-12 0.25%.
    $expected = [1 => 100.0, 2 => 100.0, 3 => 50.0, 4 => 50.0, 5 => 50.0, 6 => 50.0, 7 => 25.0, 8 => 25.0, 9 => 25.0, 10 => 25.0, 11 => 25.0, 12 => 25.0];
    foreach ($chain as $index => $beneficiary) {
        expect((float) $beneficiary->fresh()->wallet_balance)->toBe($expected[$index + 1]);
    }
    expect((float) $rows->sum('amount'))->toBe(750.0); // 7.5% of ₹10,000: 2% self + 5.5% upline.

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

test('T-110: Purchase/Repurchase Income and Store Profit Distribution both use the sale\'s own metal, not one shared rate table', function () {
    RuleValue::where('key', 'purchase_repurchase_income_rates')->update(['value' => ['self' => 2]]);
    RuleValue::where('key', 'purchase_repurchase_income_rates_gold')->update(['value' => ['self' => 3]]);
    RuleValue::where('key', 'store_profit_distribution_rates')->update(['value' => ['store_owner' => 2]]);
    RuleValue::where('key', 'store_profit_distribution_rates_gold')->update(['value' => ['store_owner' => 1]]);

    $result = makeStoreWithOwnerChain('METALSALE');
    $store = $result['store'];
    $payer = storeMember('METALSALE-PAYER');

    $goldSale = StoreSale::create([
        'store_id' => $store->id, 'member_id' => $payer->id, 'transaction_type' => 'repurchase',
        'item_name' => 'Gold Item', 'metal' => 'gold', 'quantity' => 1, 'sale_amount' => 10000, 'gst_amount' => 0,
        'total_invoice_amount' => 10000, 'payment_source' => 'cash', 'distribution_status' => 'pending', 'status' => 'confirmed',
    ]);
    app(CalculatePurchaseRepurchaseIncome::class)($goldSale);
    app(CalculateStoreProfitDistribution::class)($goldSale);

    $silverSale = StoreSale::create([
        'store_id' => $store->id, 'member_id' => $payer->id, 'transaction_type' => 'repurchase',
        'item_name' => 'Silver Item', 'metal' => 'silver', 'quantity' => 1, 'sale_amount' => 10000, 'gst_amount' => 0,
        'total_invoice_amount' => 10000, 'payment_source' => 'cash', 'distribution_status' => 'pending', 'status' => 'confirmed',
    ]);
    app(CalculatePurchaseRepurchaseIncome::class)($silverSale);
    app(CalculateStoreProfitDistribution::class)($silverSale);

    $goldSelfRow = IncomeLedgerCalculation::where('source_store_sale_id', $goldSale->id)->whereNull('level_no')->firstOrFail();
    $silverSelfRow = IncomeLedgerCalculation::where('source_store_sale_id', $silverSale->id)->whereNull('level_no')->firstOrFail();
    expect((float) $goldSelfRow->amount)->toBe(300.0); // 3% Gold.
    expect((float) $silverSelfRow->amount)->toBe(200.0); // 2% Silver.

    $goldOwnerRow = StoreProfitDistribution::where('store_sale_id', $goldSale->id)->where('beneficiary_type', 'store_owner')->firstOrFail();
    $silverOwnerRow = StoreProfitDistribution::where('store_sale_id', $silverSale->id)->where('beneficiary_type', 'store_owner')->firstOrFail();
    expect((float) $goldOwnerRow->amount)->toBe(100.0); // 1% Gold.
    expect((float) $silverOwnerRow->amount)->toBe(200.0); // 2% Silver.
});

test('Store Profit Distribution pays owner + 3 Sponsor/Direct levels on every store-attributed sale', function () {
    $result = makeStoreWithOwnerChain('SPD', 3);
    $store = $result['store'];
    $ownerChain = $result['chain']; // index 0 = owner's direct sponsor (Level 1).

    $sale = StoreSale::create([
        'store_id' => $store->id, 'member_id' => null, 'transaction_type' => 'new_sale',
        'item_name' => 'Gold Necklace', 'metal' => 'gold', 'quantity' => 1, 'sale_amount' => 50000, 'gst_amount' => 0,
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

    // Idempotent.
    app(CalculateStoreProfitDistribution::class)($sale->fresh());
    expect(StoreProfitDistribution::where('store_sale_id', $sale->id)->count())->toBe(4);
    expect((float) $result['owner']->fresh()->wallet_balance)->toBe(1000.0);
});

test('a walk-in sale pays the whole Purchase/Repurchase percentage to the Store Owner as store income (T-170, TEST.md scenario 5)', function () {
    $result = makeStoreWithOwnerChain('WALKIN7', 3);
    $sale = StoreSale::create([
        'store_id' => $result['store']->id, 'member_id' => null, 'transaction_type' => 'purchase',
        'item_name' => 'Gold Necklace', 'metal' => 'gold', 'quantity' => 1, 'sale_amount' => 50000, 'gst_amount' => 0,
        'total_invoice_amount' => 50000, 'payment_source' => 'cash', 'distribution_status' => 'pending', 'status' => 'confirmed',
    ]);

    app(CalculatePurchaseRepurchaseIncome::class)($sale);
    app(CalculatePurchaseRepurchaseIncome::class)($sale->fresh()); // idempotent

    $rows = IncomeLedgerCalculation::where('source_store_sale_id', $sale->id)->get();
    expect($rows)->toHaveCount(1);
    expect($rows->first()->beneficiary_member_id)->toBe($result['owner']->id)
        ->and($rows->first()->level_no)->toBeNull()
        ->and((float) $rows->first()->rate_percent)->toBe(7.5)   // gold: 2 + 1 + 1 + 4×0.5 + 6×0.25
        ->and((float) $rows->first()->amount)->toBe(3750.0);

    // Only the owner — none of the owner's sponsors — gets it, and the wallet line says it came from the store.
    expect((float) $result['owner']->fresh()->wallet_balance)->toBe(3750.0);
    expect((float) $result['chain'][0]->fresh()->wallet_balance)->toBe(0.0);
    expect($result['owner']->walletLedgerEntries()->latest('id')->value('description'))
        ->toBe("Walk-in store sale income — WALKIN7 Store sale #{$sale->id}");

    // The independent Earnings Verification agrees, and catches a tampered amount.
    $report = app(EarningsVerifier::class)->run(['purchase']);
    expect($report['checks']['purchase']['errors'])->toBe(0);

    $rows->first()->update(['amount' => 3400]);
    $report = app(EarningsVerifier::class)->run(['purchase']);
    expect($report['checks']['purchase']['errors'])->toBe(1);
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
        metal: 'gold',
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

    // T-168 — handed over from stock: a 5 g gold piece, priced at the seeded ₹6,000/g = ₹30,000 metal value.
    $item = app(AllocateStoreInventoryItem::class)($store, 'Gold Ring', 'gold', 5, 1, 30000, $operator);
    $sale = app(RecordPlanJewelleryDelivery::class)($benefit, $store, $item, $operator);

    expect($item->fresh()->quantity)->toBe(0);
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

    expect(fn () => app(RecordPlanJewelleryDelivery::class)($benefit->fresh(), $store, $item->fresh(), $operator))
        ->toThrow(ValidationException::class);
});

test('a bill is generated on request, once, with hallmarking per piece added before GST (T-171, the user\'s worked example)', function () {
    // 5 g gold at ₹60,000 per 10 gm, 12% making, 3% GST, ₹45 hallmark → 30,000 + 3,600 + 45 = 33,645; GST 1,009.35; total 34,654.35.
    MetalRate::create(['metal' => 'gold', 'rate_per_gram' => 6000, 'making_charge_percent' => 12, 'effective_from' => now()->toDateString(), 'created_by' => User::where('role', 'super_admin')->firstOrFail()->id]);
    RuleValue::where('key', 'store_gst_percent')->update(['value' => 3]);
    $result = makeStoreWithOwnerChain('BILL');
    $operator = User::where('role', 'super_admin')->firstOrFail();
    $price = app(PriceStoreSale::class)('gold', 5, 1);

    $sale = app(ConfirmStoreSale::class)(
        store: $result['store'], member: null, transactionType: 'purchase', itemName: 'Gold Pendant',
        inventoryItem: null, itemWeight: 5, quantity: 1, rate: $price['rate_per_gram'],
        saleAmount: $price['subtotal'], gstAmount: $price['gst_amount'], paymentSource: 'cash', operator: $operator,
        metal: 'gold', price: $price,
    );
    expect($sale->invoice)->toBeNull();
    expect((float) $sale->total_invoice_amount)->toBe(34608.0);

    // HUID count must match the quantity, and must look like a HUID.
    expect(fn () => app(GenerateStoreSaleBill::class)($sale, $operator, [['huid' => 'AB12CD', 'charge' => 45], ['huid' => 'XY34ZW', 'charge' => 45]]))
        ->toThrow(ValidationException::class);
    expect(fn () => app(GenerateStoreSaleBill::class)($sale, $operator, [['huid' => 'A!', 'charge' => 45]]))
        ->toThrow(ValidationException::class);
    // T-175 — a HUID is 6–8 letters/digits.
    expect(fn () => app(GenerateStoreSaleBill::class)($sale, $operator, [['huid' => 'AB12C', 'charge' => 45]]))
        ->toThrow(ValidationException::class);
    expect(fn () => app(GenerateStoreSaleBill::class)($sale, $operator, [['huid' => 'AB12CD345', 'charge' => 45]]))
        ->toThrow(ValidationException::class);

    $invoice = app(GenerateStoreSaleBill::class)($sale, $operator, [['huid' => 'ab12cd', 'charge' => 45]]);

    $sale = $sale->fresh();
    expect($invoice->invoice_no)->toContain('INV-')
        ->and((float) $sale->hallmark_charges)->toBe(45.0)
        ->and((float) $sale->sale_amount)->toBe(33645.0)
        ->and((float) $sale->gst_amount)->toBe(1009.35)
        ->and((float) $sale->total_invoice_amount)->toBe(34654.35)
        ->and($sale->hallmarks->pluck('huid')->all())->toBe(['AB12CD']);

    // Income stays on the metal value (walk-in → Store Owner 2% of 30,000).
    expect((float) $sale->profitDistributions()->where('beneficiary_type', 'store_owner')->value('amount'))->toBe(600.0);

    // Generated once; a second attempt is refused (open the duplicate instead).
    expect(fn () => app(GenerateStoreSaleBill::class)($sale, $operator))->toThrow(ValidationException::class);
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

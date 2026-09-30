<?php

use App\Actions\Compensation\CalculatePurchaseRepurchaseIncome;
use App\Actions\Compensation\CalculateStoreProfitDistribution;
use App\Actions\Payments\ApproveCashPayment;
use App\Actions\Registration\RegisterMember;
use App\Actions\Store\CreateStore;
use App\Models\IncomeLedgerCalculation;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\RuleValue;
use App\Models\Store;
use App\Models\StoreProfitDistribution;
use App\Models\StoreSale;
use App\Models\User;
use App\Services\EarningsVerifier;
use Illuminate\Support\Facades\DB;

/**
 * T-183 (30-09-2026) — Docs/TEST.md scenario 39: the upline parts of store income (Purchase/Repurchase L1–12, Store
 * Profit Sponsor L1–3) are paid only once the member has 10 qualified directs; that unlock is recorded once and lasts
 * for life, and before it the income lapses.
 */
function siuMember(string $customerId, ?Member $sponsor = null): Member
{
    $member = Member::create([
        'user_id' => User::factory()->create(['role' => 'member'])->id,
        'customer_id' => $customerId,
        'sponsor_id' => $sponsor?->id,
        'membership_plan_id' => MembershipPlan::where('code', 'E')->value('id'),
        'status' => 'active',
        'activated_at' => now(),
    ]);
    Payment::create(['member_id' => $member->id, 'type' => 'registration', 'amount' => 20000, 'mode' => 'cash', 'status' => 'paid', 'paid_at' => now()]);

    return $member;
}

/** Adds qualified Plan E directs under $sponsor until it has $total. */
function siuDirects(Member $sponsor, int $total): void
{
    for ($i = $sponsor->directs()->count() + 1; $i <= $total; $i++) {
        siuMember("{$sponsor->customer_id}-D{$i}", $sponsor);
    }
}

function siuSale(Store $store, ?Member $buyer): StoreSale
{
    return StoreSale::create([
        'store_id' => $store->id, 'member_id' => $buyer?->id, 'transaction_type' => $buyer ? 'purchase' : 'new_sale',
        'item_name' => 'Silver Anklet', 'metal' => 'silver', 'quantity' => 1, 'metal_value' => 10000, 'sale_amount' => 10000,
        'gst_amount' => 0, 'total_invoice_amount' => 10000, 'payment_source' => 'cash', 'distribution_status' => 'pending',
        'status' => 'confirmed',
    ]);
}

beforeEach(function () {
    $this->seed(); // store_income_min_directs = 10
    DB::table('rule_values')->where('key', 'level_income_min_directs')->update(['value' => json_encode([])]);
});

test('TEST.md scenario 39: U1 with 9 qualified directs is skipped as store_income_locked, U2 with 10 is paid and unlocked', function () {
    $u2 = siuMember('SIU-U2');
    $u1 = siuMember('SIU-U1', $u2);
    $buyer = siuMember('SIU-B', $u1);
    siuDirects($u1, 9);  // B + 8.
    siuDirects($u2, 10); // U1 + 9.
    $sale = siuSale(Store::create(['name' => 'SIU Store', 'status' => 'active']), $buyer);

    app(CalculatePurchaseRepurchaseIncome::class)($sale);

    $rows = IncomeLedgerCalculation::where('source_store_sale_id', $sale->id)->get();
    expect((float) $rows->firstWhere('level_no', null)->amount)->toBe(500.0); // self 5%, unchanged.
    $level1 = $rows->firstWhere('level_no', 1);
    expect($level1->eligibility_status)->toBe('skipped');
    expect($level1->skip_reason)->toBe('store_income_locked');
    expect($level1->beneficiary_member_id)->toBe($u1->id);
    expect((float) $level1->amount)->toBe(0.0);
    expect((float) $rows->firstWhere('level_no', 2)->amount)->toBe(100.0); // 1%.
    expect($rows->firstWhere('level_no', 3)->skip_reason)->toBe('chain_too_short');

    expect($u1->fresh()->store_income_unlocked_at)->toBeNull();
    expect($u2->fresh()->store_income_unlocked_at)->not->toBeNull();
    expect((float) $u1->fresh()->wallet_balance)->toBe(0.0);
});

test('TEST.md scenario 39: a 10th direct\'s confirmed registration unlocks U1 at once, and the unlock survives losing a direct', function () {
    $u1 = siuMember('SIU-L1');
    $buyer = siuMember('SIU-LB', $u1);
    siuDirects($u1, 9);

    $tenth = app(RegisterMember::class)([
        'sponsor_code' => $u1->customer_id, 'placement_side' => 'left', 'gender' => 'male', 'name' => 'Tenth Direct',
        'email' => 'siu-tenth@example.test', 'mobile' => '9444400010',
        'membership_plan_id' => MembershipPlan::where('code', 'E')->value('id'), 'payment_mode' => 'cash',
    ]);
    expect($u1->fresh()->store_income_unlocked_at)->toBeNull();

    app(ApproveCashPayment::class)($tenth->payments()->firstOrFail(), User::where('role', 'super_admin')->firstOrFail());
    expect($u1->fresh()->store_income_unlocked_at)->not->toBeNull();

    // A direct is cancelled — back to 9 — but store income stays unlocked for life.
    $u1->directs()->where('customer_id', 'like', 'SIU-L1-D%')->first()->update(['status' => 'cancelled']);
    $sale = siuSale(Store::create(['name' => 'SIU Store 2', 'status' => 'active']), $buyer);
    app(CalculatePurchaseRepurchaseIncome::class)($sale);

    $level1 = IncomeLedgerCalculation::where('source_store_sale_id', $sale->id)->where('level_no', 1)->firstOrFail();
    expect($level1->eligibility_status)->toBe('paid');
    expect((float) $level1->amount)->toBe(200.0); // 2%.
});

test('TEST.md scenario 39: Store Profit pays the owner, gives the locked S1 no row, and pays S2 ₹25', function () {
    $s2 = siuMember('SIU-S2');
    $s1 = siuMember('SIU-S1', $s2);
    $owner = siuMember('SIU-O', $s1);
    siuDirects($s1, 3);  // O + 2.
    siuDirects($s2, 10); // S1 + 9.
    $store = app(CreateStore::class)('SIU Owner Store', $owner->user, '9999999999', 'Test City', 1000000, 0, User::where('role', 'super_admin')->firstOrFail());
    $sale = siuSale($store, null);

    app(CalculateStoreProfitDistribution::class)($sale);

    $rows = StoreProfitDistribution::where('store_sale_id', $sale->id)->get()->keyBy('beneficiary_type');
    expect((float) $rows['store_owner']->amount)->toBe(200.0);
    expect($rows->has('sponsor_level_1'))->toBeFalse();
    expect((float) $rows['sponsor_level_2']->amount)->toBe(25.0);
    expect($rows['sponsor_level_2']->beneficiary_member_id)->toBe($s2->id);
});

test('a rule version without the setting (or 0) pays every level and records no unlock', function () {
    RuleValue::where('key', 'store_income_min_directs')->delete();
    $u1 = siuMember('SIU-N1');
    $buyer = siuMember('SIU-NB', $u1);
    $sale = siuSale(Store::create(['name' => 'SIU Store 3', 'status' => 'active']), $buyer);

    app(CalculatePurchaseRepurchaseIncome::class)($sale);

    expect(IncomeLedgerCalculation::where('source_store_sale_id', $sale->id)->where('level_no', 1)->value('eligibility_status'))->toBe('paid');
    expect($u1->fresh()->store_income_unlocked_at)->toBeNull();
});

test('earnings:verify accepts the locked/unlocked rows, and catches a paid-while-locked or skipped-while-unlocked level', function () {
    $u2 = siuMember('SIU-V2');
    $u1 = siuMember('SIU-V1', $u2);
    $buyer = siuMember('SIU-VB', $u1);
    siuDirects($u2, 10);
    $sale = siuSale(Store::create(['name' => 'SIU Store 4', 'status' => 'active']), $buyer);
    app(CalculatePurchaseRepurchaseIncome::class)($sale);

    expect(app(EarningsVerifier::class)->run(['purchase'])['checks']['purchase']['errors'])->toBe(0);

    // Pretend U1 had unlocked long before the sale: the skipped L1 is now wrong.
    DB::table('members')->where('id', $u1->id)->update(['store_income_unlocked_at' => now()->subYear()]);
    $messages = implode(' | ', array_column(app(EarningsVerifier::class)->run(['purchase'])['checks']['purchase']['findings'], 'message'));
    expect($messages)->toContain("Skipped as 'store_income_locked', but SIU-V1 had already unlocked store income.");

    // And U2 unlocking only after the sale makes its paid L2 wrong.
    DB::table('members')->where('id', $u2->id)->update(['store_income_unlocked_at' => now()->addYear()]);
    $messages = implode(' | ', array_column(app(EarningsVerifier::class)->run(['purchase'])['checks']['purchase']['findings'], 'message'));
    expect($messages)->toContain('Paid to SIU-V2 before they unlocked store income');
});

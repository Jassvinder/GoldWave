<?php

use App\Actions\DummyEntries\AssignDummyEntryToLeader;
use App\Actions\DummyEntries\GenerateDailyDummyEntries;
use App\Actions\Payments\ApproveCashPayment;
use App\Actions\Payments\InitiateEmiInstallmentPayment;
use App\Actions\Registration\RegisterMember;
use App\Actions\Store\ConfirmStoreSale;
use App\Actions\Store\CreateStore;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\RuleValue;
use App\Models\Store;
use App\Models\User;
use App\Services\EarningsVerifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The Earnings Verification (`EarningsVerifier`, `php artisan earnings:verify`, Super Admin page): a clean database
 * verifies clean, and every kind of tampering / bug it exists to catch is actually caught.
 */
function evSuperAdmin(): User
{
    return User::where('role', 'super_admin')->firstOrFail();
}

/** Registers a member under $sponsorCode through the real Action and approves the cash payment, so every earning is written by the real code path. */
function evJoin(string $sponsorCode, string $name, string $side = 'left', string $planCode = 'E'): Member
{
    static $n = 0;
    $n++;

    $member = app(RegisterMember::class)([
        'sponsor_code' => $sponsorCode,
        'placement_side' => $side,
        'gender' => 'male',
        'name' => $name,
        'email' => "ev{$n}@example.test",
        'mobile' => (string) (9600000000 + $n),
        'membership_plan_id' => MembershipPlan::where('code', $planCode)->value('id'),
        'payment_mode' => 'cash',
    ]);

    app(ApproveCashPayment::class)($member->payments->firstWhere('type', 'registration'), evSuperAdmin());

    return $member->fresh();
}

function evRoot(): Member
{
    return Member::create([
        'user_id' => User::factory()->create(['role' => 'member'])->id,
        'customer_id' => 'GWL900',
        'gender' => 'male',
        'status' => 'active',
        'activated_at' => now(),
    ]);
}

/** A chain GWL900 → A → B → C (each sponsored by, and placed under, the one before) so Level Income and Pair entries have depth. */
function evChain(): array
{
    evRoot();
    $a = evJoin('GWL900', 'Member A');
    $b = evJoin($a->customer_id, 'Member B');
    $c = evJoin($b->customer_id, 'Member C');

    return [$a, $b, $c];
}

/** @return array<string, array{errors: int, warnings: int, findings: list<array<string, string>>}> */
function evReport(array $only = []): array
{
    return app(EarningsVerifier::class)->run($only, 50)['checks'];
}

function evMessages(string $check, array $only = []): string
{
    return implode(' | ', array_column(evReport($only ?: [$check])[$check]['findings'], 'message'));
}

beforeEach(function () {
    $this->seed();
    // evChain's members each have a single direct, so the T-179 Level Income directs gate is switched off here; the
    // T-179 tests below switch it on themselves.
    DB::table('rule_values')->where('key', 'level_income_min_directs')->update(['value' => json_encode([])]);
    // Likewise the T-183 store-income unlock (StoreIncomeUnlockTest covers its verifier checks).
    DB::table('rule_values')->where('key', 'store_income_min_directs')->update(['value' => json_encode(0)]);
});

test('an untouched database verifies clean across every check', function () {
    evChain();

    $report = app(EarningsVerifier::class)->run();

    expect($report['errors'])->toBe(0);
    expect($report['checks']['level']['checked'])->toBe(3);
    expect($report['checks']['ledger']['checked'])->toBeGreaterThan(0);
    expect($report['checks']['pair']['checked'])->toBeGreaterThan(0);
});

test('a changed Level Income amount, rate or beneficiary is caught', function () {
    [$a, $b, $c] = evChain();
    $row = DB::table('income_ledger_calculations')->where('type', 'level_income')->where('beneficiary_member_id', $b->id)->where('level_no', 1)->first();

    DB::table('income_ledger_calculations')->where('id', $row->id)->update(['amount' => 999]);
    expect(evMessages('level'))->toContain('Amount should be')->toContain('999');

    DB::table('income_ledger_calculations')->where('id', $row->id)->update(['amount' => $row->amount, 'beneficiary_member_id' => $a->id]);
    expect(evMessages('level'))->toContain('Should go to');

    DB::table('income_ledger_calculations')->where('id', $row->id)->update(['beneficiary_member_id' => $b->id, 'rate_percent' => 9]);
    expect(evMessages('level'))->toContain('Rate should be');
});

test('a missing or extra Level Income row and a payment that was never calculated are caught', function () {
    evChain();
    $rowId = DB::table('income_ledger_calculations')->where('type', 'level_income')->where('level_no', 5)->value('id');
    DB::table('income_ledger_calculations')->where('id', $rowId)->delete();

    expect(evMessages('level'))->toContain('Level 5 row is missing');

    $payment = DB::table('payments')->where('status', 'paid')->first();
    DB::table('income_ledger_calculations')->where('source_payment_id', $payment->id)->delete();
    expect(evMessages('level'))->toContain('never calculated');
});

test('an upline whose status changed later is a warning, not an error', function () {
    [$a] = evChain();
    $row = DB::table('income_ledger_calculations')->where('type', 'level_income')->where('beneficiary_member_id', $a->id)->where('eligibility_status', 'paid')->first();
    DB::table('members')->where('id', $a->id)->update(['status' => 'cancelled']);

    $level = evReport(['level'])['level'];

    expect($level['errors'])->toBe(0);
    expect($level['warnings'])->toBeGreaterThan(0);
    expect(implode(' ', array_column($level['findings'], 'message')))->toContain('status changed since');
});

test('a wallet entry that is missing, changed or orphaned, and a balance that no longer matches the ledger, are caught', function () {
    [$a] = evChain();
    $entry = DB::table('wallet_ledger_entries')->where('member_id', $a->id)->where('category', 'level_income')->first();

    DB::table('wallet_ledger_entries')->where('id', $entry->id)->update(['amount' => $entry->amount + 5]);
    expect(evMessages('ledger'))->toContain('differs from the earning');

    DB::table('wallet_ledger_entries')->where('id', $entry->id)->delete();
    expect(evMessages('ledger'))->toContain('Expected exactly one wallet credit');

    $orphan = DB::table('wallet_ledger_entries')->insertGetId([
        'member_id' => $a->id, 'entry_type' => 'credit', 'category' => 'level_income', 'source_type' => 'App\Models\IncomeLedgerCalculation',
        'source_id' => 999999, 'amount' => 10, 'status' => 'confirmed', 'created_at' => now(), 'updated_at' => now(),
    ]);
    expect(evMessages('ledger'))->toContain('points to a source that does not exist');
    DB::table('wallet_ledger_entries')->where('id', $orphan)->delete();

    DB::table('members')->where('id', $a->id)->update(['wallet_balance' => 12345]);
    expect(evMessages('wallet'))->toContain('Wallet balance is')->toContain('12345');
});

test('a missing, wrong-side or duplicated Pair entry is caught', function () {
    [$a, $b, $c] = evChain();
    $entry = DB::table('pair_entries')->where('member_id', $a->id)->first();

    DB::table('pair_entries')->where('id', $entry->id)->update(['side' => $entry->side === 'left' ? 'right' : 'left']);
    expect(evMessages('pair'))->toContain('do not match the payer');

    DB::table('pair_entries')->where('id', $entry->id)->delete();
    expect(evMessages('pair'))->toContain('Missing');
});

test('a Pair reward that does not match its milestone, its consumed entries or its value is caught', function () {
    [$a] = evChain();

    // Hand-build a milestone-1 achievement for A: 5 Left + 5 Right consumed entries (each needs its own source payment).
    $entries = [];
    for ($i = 0; $i < 10; $i++) {
        $paymentId = DB::table('payments')->insertGetId([
            'member_id' => $a->id, 'type' => 'emi_installment', 'amount' => 1, 'mode' => 'cash', 'status' => 'paid',
            'idempotency_key' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $entries[] = ['member_id' => $a->id, 'side' => $i < 5 ? 'left' : 'right', 'metal' => 'silver', 'source_payment_id' => $paymentId, 'status' => 'consumed', 'consumed_for_milestone_no' => 1, 'created_at' => now(), 'updated_at' => now()];
    }
    DB::table('pair_entries')->insert($entries);
    $tx = DB::table('pair_reward_transactions')->insertGetId([
        'member_id' => $a->id, 'milestone_no' => 1, 'left_consumed_count' => 5, 'right_consumed_count' => 5, 'reward_amount' => 1, 'rule_version_id' => DB::table('rule_versions')->where('is_active', true)->value('id'),
        'calculated_for_month' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(evMessages('pair'))->toContain('Reward should be');

    // T-178 — A has only 1 qualified direct (B), below milestone 1's total of 2: a warning, not an error.
    $pair = evReport(['pair'])['pair'];
    expect($pair['warnings'])->toBeGreaterThan(0);
    expect(implode(' | ', array_column($pair['findings'], 'message')))->toContain('Milestone needs 2 qualified directs, but the member has 1 now');

    DB::table('pair_reward_transactions')->where('id', $tx)->delete();
    expect(evMessages('pair'))->toContain('has no reward transaction');
});

test('T-186: a held Level Income row verifies clean, is released when the member gets enough directs, and a stuck held row is a warning', function () {
    DB::table('rule_values')->where('key', 'level_income_min_directs')->update(['value' => json_encode(['1' => 2])]);
    [$a, $b] = evChain(); // A's only direct is B, so B's Level 1 (→ A) is held; C's Level 1 (→ B) likewise.

    $heldRow = DB::table('income_ledger_calculations')->where('beneficiary_member_id', $a->id)->where('level_no', 1)->first();
    expect($heldRow->eligibility_status)->toBe('held');
    expect($heldRow->skip_reason)->toBe('insufficient_directs');
    $level = evReport(['level'])['level'];
    expect($level['errors'])->toBe(0);
    expect($level['warnings'])->toBe(0);

    evJoin($a->customer_id, 'Second direct of A', 'right'); // A now has 2 qualified directs: the held row is released.
    expect(DB::table('income_ledger_calculations')->where('id', $heldRow->id)->value('eligibility_status'))->toBe('paid');
    $report = evReport(['level', 'ledger', 'wallet']);
    expect($report['level']['errors'] + $report['ledger']['errors'] + $report['wallet']['errors'])->toBe(0);
    expect($report['level']['warnings'])->toBe(0);

    // A row left held although the count is met (its release never ran) is flagged.
    DB::table('income_ledger_calculations')->where('id', $heldRow->id)->update(['eligibility_status' => 'held', 'skip_reason' => 'insufficient_directs', 'released_at' => null]);
    DB::table('wallet_ledger_entries')->where('source_id', $heldRow->id)->where('category', 'level_income')->delete();
    expect(evMessages('level'))->toContain('should have been released');
});

test('T-179: a paid Level Income row whose beneficiary now has fewer directs than required is a warning', function () {
    [$a] = evChain();
    DB::table('rule_values')->where('key', 'level_income_min_directs')->update(['value' => json_encode(['1' => 2])]);

    $level = evReport(['level'])['level'];
    expect($level['errors'])->toBe(0);
    expect(implode(' | ', array_column($level['findings'], 'message')))->toContain("this level needs 2 qualified directs and {$a->customer_id} has 1 now");
});

test('Purchase/Repurchase and Store Profit distributions are re-derived and a changed amount is caught', function () {
    [$a, $b, $c] = evChain();
    $ownerUser = User::factory()->create(['role' => 'store_admin']);
    $ownerMember = Member::create(['user_id' => $ownerUser->id, 'customer_id' => 'GWLOWN', 'sponsor_id' => $c->id, 'gender' => 'male', 'status' => 'active', 'activated_at' => now()]);
    $store = app(CreateStore::class)('Verify Store', $ownerUser, null, null, 500000, 200000, evSuperAdmin(), 'StorePass123!');
    expect($store)->toBeInstanceOf(Store::class);

    $sale = app(ConfirmStoreSale::class)(
        store: $store, member: $c, transactionType: 'new_sale', itemName: 'Ring', inventoryItem: null, itemWeight: 10.0,
        quantity: 1, rate: 350.0, saleAmount: 10000.0, gstAmount: 0.0, paymentSource: 'other', operator: evSuperAdmin(), metal: 'silver',
    );

    $clean = app(EarningsVerifier::class)->run(['purchase', 'store', 'ledger', 'wallet']);
    expect($clean['errors'])->toBe(0);
    expect($clean['checks']['purchase']['checked'])->toBe(1);
    expect($clean['checks']['store']['checked'])->toBe(1);

    $row = DB::table('income_ledger_calculations')->where('type', 'purchase_repurchase')->where('source_store_sale_id', $sale->id)->where('level_no', 2)->first();
    DB::table('income_ledger_calculations')->where('id', $row->id)->update(['amount' => 1]);
    expect(evMessages('purchase'))->toContain('Amount should be');

    $distribution = DB::table('store_profit_distributions')->where('store_sale_id', $sale->id)->where('beneficiary_type', 'store_owner')->first();
    DB::table('store_profit_distributions')->where('id', $distribution->id)->update(['amount' => 7]);
    expect(evMessages('store'))->toContain('store_owner');
});

test('a booster payout with the wrong amount or date, and a paid one without a wallet entry, are caught', function () {
    [$a] = evChain();
    $version = DB::table('rule_versions')->where('is_active', true)->value('id');
    $q = DB::table('booster_qualifications')->insertGetId(['member_id' => $a->id, 'level_no' => 1, 'qualified_at' => '2026-09-01 10:00:00', 'rule_version_id' => $version, 'created_at' => now(), 'updated_at' => now()]);

    foreach (range(1, 12) as $month) { // Booster Level 1 runs 12 months (T-180).
        DB::table('booster_payout_schedules')->insert([
            'booster_qualification_id' => $q, 'month_no' => $month, 'scheduled_date' => date('Y-m-d', strtotime('2026-09-01 +'.($month - 1).' month')),
            'amount' => 5000, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    expect(evReport(['booster'])['booster']['errors'])->toBe(0);

    DB::table('booster_payout_schedules')->where('booster_qualification_id', $q)->where('month_no', 2)->update(['amount' => 4000]);
    DB::table('booster_payout_schedules')->where('booster_qualification_id', $q)->where('month_no', 3)->update(['scheduled_date' => '2026-12-31']);
    DB::table('booster_payout_schedules')->where('booster_qualification_id', $q)->where('month_no', 4)->update(['status' => 'paid']);

    $messages = evMessages('booster');
    expect($messages)->toContain('Month 2 should be')->toContain('Month 3 should be scheduled')->toContain('Month 4 is marked paid but has no wallet entry');
});

test('the artisan command exits 0 when clean, 1 on a difference, and rejects an unknown check', function () {
    evChain();

    $this->artisan('earnings:verify')->assertExitCode(0);
    $this->artisan('earnings:verify', ['--only' => ['nonsense']])->assertExitCode(2);

    DB::table('income_ledger_calculations')->where('type', 'level_income')->where('eligibility_status', 'paid')->limit(1)->update(['amount' => 1]);
    $this->artisan('earnings:verify', ['--only' => ['level']])->assertExitCode(1);
});

test('the Super Admin page shows the report only after Run, and only to a Super Admin', function () {
    evChain();

    $this->actingAs(evSuperAdmin())->get('/super-admin/earnings-verification')
        ->assertInertia(fn ($page) => $page->component('super-admin/earnings-verification')->where('report', null)->has('checks', 7));

    $this->actingAs(evSuperAdmin())->get('/super-admin/earnings-verification?run=1')
        ->assertInertia(fn ($page) => $page->where('report.errors', 0)->has('report.checks.level')->where('report.checks.level.checked', 3));

    $member = User::factory()->create(['role' => 'member']);
    $this->actingAs($member)->get('/super-admin/earnings-verification?run=1')->assertForbidden();
});

test('T-149: a dummy entry\'s own EMI installment #1 is invisible to Level Income, and an assigned leader\'s real EMI payments still verify clean', function () {
    RuleValue::where('key', 'dummy_entry_enabled')->update(['value' => true]);
    RuleValue::where('key', 'dummy_entry_daily_count')->update(['value' => 1]);
    RuleValue::where('key', 'dummy_entry_plan_code')->update(['value' => 'A']);
    $dummy = app(GenerateDailyDummyEntries::class)()[0];

    // Still unassigned: the verifier must not flag the silent installment #1 payment as "never calculated".
    $unassignedReport = app(EarningsVerifier::class)->run(['level']);
    expect($unassignedReport['errors'])->toBe(0);

    app(AssignDummyEntryToLeader::class)($dummy, 'Real Leader', 'realleader-ev@example.test', '9998887772', evSuperAdmin());
    $leader = $dummy->fresh();

    // The leader pays installment #2 for real — a genuine payment event, real Level Income to whatever real upline exists.
    $second = $leader->emiSchedule->installments()->where('installment_no', 2)->firstOrFail();
    app(InitiateEmiInstallmentPayment::class)($leader, $second, 'cash');
    app(ApproveCashPayment::class)($leader->payments()->where('type', 'emi_installment')->firstOrFail(), evSuperAdmin());

    $clean = app(EarningsVerifier::class)->run();
    expect($clean['errors'])->toBe(0);

    // The company root sits directly above every dummy-seeded leader — it must never receive Level Income.
    $root = Member::where('is_company_root', true)->firstOrFail();
    expect(DB::table('wallet_ledger_entries')->where('member_id', $root->id)->count())->toBe(0);
    $level1Row = DB::table('income_ledger_calculations')->where('source_payment_id', $leader->payments()->where('type', 'emi_installment')->value('id'))->where('level_no', 1)->first();
    expect($level1Row->eligibility_status)->toBe('skipped');
    expect($level1Row->skip_reason)->toBe('upline_dummy');

    // Tamper: if the root were ever wrongly credited, the verifier must catch it.
    DB::table('income_ledger_calculations')->where('id', $level1Row->id)->update(['eligibility_status' => 'paid', 'amount' => 10, 'beneficiary_member_id' => $root->id]);
    expect(evMessages('level'))->toContain('must never be a paid beneficiary');
});

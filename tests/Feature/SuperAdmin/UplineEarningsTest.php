<?php

use App\Actions\Compensation\CalculateLevelIncome;
use App\Actions\Compensation\CalculatePurchaseRepurchaseIncome;
use App\Actions\Compensation\CalculateStoreProfitDistribution;
use App\Actions\Store\CreateStore;
use App\Models\DrawExecution;
use App\Models\DrawGroup;
use App\Models\DrawGroupMonthConfig;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\PairEntry;
use App\Models\Payment;
use App\Models\RuleValue;
use App\Models\StoreSale;
use App\Models\User;

/**
 * T-238 — Docs/TEST.md scenario 49: the Super Admin "Upline Earnings" page lists every earning that went up the
 * network because of one member — paid, held and skipped rows, the Sponsor's draw prize and the Pair entries.
 */
function uetMember(string $customerId, ?Member $sponsor = null): Member
{
    return Member::create([
        'user_id' => User::factory()->create(['role' => 'member', 'name' => "Name {$customerId}"])->id,
        'customer_id' => $customerId,
        'sponsor_id' => $sponsor?->id,
        'membership_plan_id' => MembershipPlan::where('code', 'E')->firstOrFail()->id,
        'status' => 'active',
        'activated_at' => now(),
    ]);
}

beforeEach(function () {
    $this->seed();

    // A ← B ← X. Level 2 needs 4 qualified directs, which A doesn't have, so A's share is held (T-186).
    RuleValue::where('key', 'level_income_min_directs')->update(['value' => ['2' => 4]]);
    $this->a = uetMember('UET-A');
    $this->b = uetMember('UET-B', $this->a);
    $this->x = uetMember('UET-X', $this->b);

    $payment = Payment::create(['member_id' => $this->x->id, 'type' => 'registration', 'amount' => 20000, 'mode' => 'cash', 'status' => 'paid', 'paid_at' => now()]);
    app(CalculateLevelIncome::class)($payment);

    PairEntry::create(['member_id' => $this->b->id, 'side' => 'left', 'metal' => 'silver', 'source_payment_id' => $payment->id, 'status' => 'consumed', 'consumed_for_milestone_no' => 1]);
    PairEntry::create(['member_id' => $this->a->id, 'side' => 'right', 'metal' => 'silver', 'source_payment_id' => $payment->id, 'status' => 'unused']);

    $group = DrawGroup::create(['group_no' => 99, 'size' => 1, 'cycle_started_month' => now()->startOfMonth(), 'status' => 'active']);
    DrawGroupMonthConfig::create(['draw_group_id' => $group->id, 'cycle_month_no' => 1, 'prize_name' => 'Silver Jewellery', 'prize_value' => 20000, 'metal_type' => 'silver']);
    DrawExecution::create([
        'draw_group_id' => $group->id, 'cycle_month_no' => 1, 'winner_no' => 1, 'executed_at' => now(),
        'winner_member_id' => $this->x->id, 'rng_proof' => json_encode(['algorithm' => 'test']),
        'upline_benefit_member_id' => $this->b->id, 'status' => 'executed',
    ]);
});

test('the page shows who received what because of the member, with totals and a per-upline summary (T-238)', function () {
    $this->actingAs(User::where('role', 'super_admin')->firstOrFail())
        ->get("/super-admin/upline-earnings?member={$this->x->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('super-admin/upline-earnings')
            ->where('member.customer_id', 'UET-X')
            // B: Level 1 = 5% of ₹20,000. A: Level 2 = 2% = ₹400, held. Levels 3-12: no upline, skipped.
            ->where('totals.paid', '1000.00')
            ->where('totals.held', '400.00')
            ->where('totals.skipped', 10)
            ->where('totals.prizes', 1)
            ->where('totals.pair_entries', 2)
            ->has('summary.receivers', 2)
            ->where('summary.pair_only_count', 0)
            ->where('summary.receivers.0.customer_id', 'UET-B')
            ->where('summary.receivers.0.sponsor_level', 1)
            ->where('summary.receivers.0.paid', '1000.00')
            ->where('summary.receivers.0.prizes', 1)
            ->where('summary.receivers.0.pair_entries', 1)
            ->where('summary.receivers.0.pair_used', 1)
            ->where('summary.receivers.1.customer_id', 'UET-A')
            ->where('summary.receivers.1.sponsor_level', 2)
            ->where('summary.receivers.1.paid', '0.00')
            ->where('summary.receivers.1.held', '400.00')
            ->where('rows.total', 15));
});

test('a binary upline outside the sponsor chain with only a Pair entry is counted, not listed, in the summary (T-238)', function () {
    $outsider = uetMember('UET-BIN');
    PairEntry::create(['member_id' => $outsider->id, 'side' => 'left', 'metal' => 'silver', 'source_payment_id' => Payment::where('member_id', $this->x->id)->value('id'), 'status' => 'unused']);

    $this->actingAs(User::where('role', 'super_admin')->firstOrFail())
        ->get("/super-admin/upline-earnings?member={$this->x->id}")
        ->assertInertia(fn ($page) => $page
            ->has('summary.receivers', 2)
            ->where('summary.pair_only_count', 1)
            ->where('totals.pair_entries', 3)
            ->where('rows.total', 16));
});

test('the detail list filters by status and type; totals stay for the whole member (T-238)', function () {
    $admin = User::where('role', 'super_admin')->firstOrFail();

    $this->actingAs($admin)
        ->get("/super-admin/upline-earnings?member={$this->x->id}&status=held")
        ->assertInertia(fn ($page) => $page
            ->where('rows.total', 1)
            ->where('rows.data.0.beneficiary_customer_id', 'UET-A')
            ->where('rows.data.0.relation', 'Level 2')
            ->where('rows.data.0.amount', '400.00')
            ->where('rows.data.0.reason', 'Waiting for qualified directs')
            ->where('totals.paid', '1000.00'));

    $this->actingAs($admin)
        ->get("/super-admin/upline-earnings?member={$this->x->id}&type=pair_entry")
        ->assertInertia(fn ($page) => $page
            ->where('rows.total', 2)
            ->where('rows.data', fn ($rows) => collect($rows)->pluck('status')->sort()->values()->all() === ['pair_unused', 'pair_used']));

    $this->actingAs($admin)
        ->get("/super-admin/upline-earnings?member={$this->x->id}&type=draw_benefit")
        ->assertInertia(fn ($page) => $page
            ->where('rows.data.0.beneficiary_customer_id', 'UET-B')
            ->where('rows.data.0.amount', '20000.00'));

    $this->actingAs($admin)
        ->get("/super-admin/upline-earnings?member={$this->x->id}&status=skipped")
        ->assertInertia(fn ($page) => $page
            ->where('rows.total', 10)
            ->where('rows.data.0.beneficiary_id', null)
            ->where('rows.data.0.reason', 'No upline at this level'));
});

test('a store purchase shows the member\'s own share, the uplines\' Purchase income and the store owner\'s profit share (T-238)', function () {
    RuleValue::where('key', 'purchase_repurchase_income_rates')->update(['value' => ['self' => 2, '1' => 1]]);
    RuleValue::where('key', 'store_profit_distribution_rates')->update(['value' => ['store_owner' => 2]]);
    $admin = User::where('role', 'super_admin')->firstOrFail();
    $owner = uetMember('UET-OWNER');
    $store = app(CreateStore::class)('UET Store', $owner->user, '9999999999', 'Test City', 1000000, 0, $admin);

    $sale = StoreSale::create([
        'store_id' => $store->id, 'member_id' => $this->x->id, 'transaction_type' => 'repurchase',
        'item_name' => 'Silver Anklet', 'metal' => 'silver', 'quantity' => 1, 'sale_amount' => 10000, 'gst_amount' => 0,
        'total_invoice_amount' => 10000, 'payment_source' => 'cash', 'distribution_status' => 'pending', 'status' => 'confirmed',
    ]);
    app(CalculatePurchaseRepurchaseIncome::class)($sale);
    app(CalculateStoreProfitDistribution::class)($sale);

    $this->actingAs($admin)
        ->get("/super-admin/upline-earnings?member={$this->x->id}&type=store_profit")
        ->assertInertia(fn ($page) => $page
            ->where('rows.total', 1)
            ->where('rows.data.0.beneficiary_customer_id', 'UET-OWNER')
            ->where('rows.data.0.relation', 'Store owner')
            ->where('rows.data.0.base_amount', '10000.00')
            ->where('rows.data.0.amount', '200.00'));

    // Self 2% = ₹200 paid; B's Level 1 is skipped because B has not unlocked store income (T-183).
    $this->actingAs($admin)
        ->get("/super-admin/upline-earnings?member={$this->x->id}&type=purchase_repurchase")
        ->assertInertia(fn ($page) => $page
            ->where('rows.total', 13)
            ->where('rows.data', fn ($rows) => collect($rows)->contains(fn ($row) => $row['relation'] === 'Self' && $row['amount'] === '200.00' && $row['status'] === 'paid')
                && collect($rows)->contains(fn ($row) => $row['relation'] === 'Level 1' && $row['beneficiary_customer_id'] === 'UET-B' && $row['status'] === 'skipped' && str_starts_with($row['reason'], 'Store income not unlocked')))
            ->where('summary.receivers.0.customer_id', 'UET-X')
            ->where('summary.receivers.0.sponsor_level', 0)
            ->where('summary.receivers.0.paid', '200.00'));
});

test('search opens the one matching member, or lists several to pick from (T-238)', function () {
    $admin = User::where('role', 'super_admin')->firstOrFail();

    $this->actingAs($admin)->get('/super-admin/upline-earnings?search=uet-x')
        ->assertInertia(fn ($page) => $page->where('member.id', $this->x->id)->where('matches', []));

    $this->actingAs($admin)->get('/super-admin/upline-earnings?search=UET-')
        ->assertInertia(fn ($page) => $page->where('member', null)->has('matches', 3));

    $this->actingAs($admin)->get('/super-admin/upline-earnings')
        ->assertInertia(fn ($page) => $page->where('member', null)->where('matches', [])->where('rows', null));
});

test('only the Super Admin can open Upline Earnings (T-238)', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get('/super-admin/upline-earnings')
        ->assertForbidden();

    $this->actingAs(User::where('role', 'super_admin')->firstOrFail())
        ->get("/super-admin/members/{$this->x->id}")
        ->assertInertia(fn ($page) => $page->where('can_view_upline_earnings', true));
});

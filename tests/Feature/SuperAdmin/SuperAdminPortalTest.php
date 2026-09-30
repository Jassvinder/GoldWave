<?php

use App\Actions\Draw\ExecuteMonthlyDraw;
use App\Actions\Draw\GenerateDrawGroups;
use App\Actions\DummyEntries\GenerateDailyDummyEntries;
use App\Actions\Payout\SubmitPayoutRequest;
use App\Actions\Profile\SubmitProfileChangeRequest;
use App\Actions\Store\CreateStore;
use App\Events\PaymentConfirmed;
use App\Models\CompanyDelivery;
use App\Models\CompanyWalletLedgerEntry;
use App\Models\DrawGroupMonthConfig;
use App\Models\EmiInstallment;
use App\Models\EmiSchedule;
use App\Models\IncomeLedgerCalculation;
use App\Models\Member;
use App\Models\MemberBankDetail;
use App\Models\MembershipPlan;
use App\Models\MetalRate;
use App\Models\PairEntry;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\RuleValue;
use App\Models\RuleVersion;
use App\Models\Store;
use App\Models\User;
use App\Notifications\PayoutRequestSubmitted;
use App\Services\CompanyFinancialSummary;
use App\Services\CompanyWalletService;
use App\Services\Notifier;
use App\Services\RuleVersionService;
use App\Services\WalletLedgerService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * T-017 — HTTP-layer coverage (routes, role gating, Form Requests) for the
 * Super Admin Portal's controllers built around T-017's pre-coding-pass
 * Actions. The Actions themselves are covered directly (not via HTTP) in
 * `tests/Feature/SuperAdmin/SuperAdminSettingsTest.php` — this file exercises
 * the routes/controllers wrapping them instead of duplicating that coverage.
 */
function spSuperAdmin(): User
{
    return User::where('role', 'super_admin')->firstOrFail();
}

function spMember(string $customerId): Member
{
    $user = User::factory()->create(['role' => 'member']);
    $plan = MembershipPlan::where('code', 'E')->first();

    return Member::create([
        'user_id' => $user->id,
        'customer_id' => $customerId,
        'membership_plan_id' => $plan?->id,
        'status' => 'active',
        'activated_at' => now(),
    ]);
}

/** T-132 — session state `password.confirm` reads; a Super Admin who re-entered their password just now. */
function spRecentlyConfirmedPassword(): array
{
    return ['auth.password_confirmed_at' => time()];
}

beforeEach(function () {
    $this->seed();
});

test('a non-super-admin is forbidden from every super admin portal route', function () {
    $member = spMember('SP-MEMBER');

    $this->actingAs($member->user)->get('/super-admin/admin-users')->assertForbidden();
});

test('a super admin sees the real S01 System Dashboard at the shared /dashboard route', function () {
    $response = $this->actingAs(spSuperAdmin())->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('super-admin/dashboard'));
});

test('the dashboard and Member Management show the same Total Members, excluding company dummy entries (T-157)', function () {
    spMember('SP-TOTAL-1');
    Member::create([
        'user_id' => User::factory()->create(['role' => 'member'])->id,
        'customer_id' => 'SP-TOTAL-DUMMY',
        'is_company_dummy' => true,
        'dummy_status' => 'unassigned',
        'status' => 'active',
    ]);
    $expected = Member::where('is_company_dummy', false)->count();

    $this->actingAs(spSuperAdmin())
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('members.total', $expected));

    $this->actingAs(spSuperAdmin())
        ->get('/super-admin/members')
        ->assertInertia(fn ($page) => $page
            ->where('stats.total', $expected)
            // T-159 — the plan's marketing name, never the letter code; the filter keeps the code as its value.
            ->where('members.data.0.customer_id', 'SP-TOTAL-1')
            ->where('members.data.0.plan', MembershipPlan::where('code', 'E')->value('name'))
            ->where('plan_options.0', ['code' => 'A', 'name' => MembershipPlan::where('code', 'A')->value('name')]));
});

test('the Financial Summary totals money in and member earnings for the chosen period (T-172)', function () {
    $member = spMember('SP-FIN-1');
    Payment::create([
        'member_id' => $member->id, 'type' => 'registration', 'amount' => 20000,
        'mode' => 'cash', 'status' => 'paid', 'paid_at' => '2030-01-15 10:00:00',
    ]);
    Payment::create([
        'member_id' => $member->id, 'type' => 'registration', 'amount' => 999,
        'mode' => 'cash', 'status' => 'paid', 'paid_at' => '2030-03-01 10:00:00', // outside the period
    ]);
    $credit = app(WalletLedgerService::class)->credit($member, 'level_income', 500, null, 'test credit');
    $credit->forceFill(['created_at' => '2030-01-20 10:00:00'])->save();

    $this->actingAs(spSuperAdmin())
        ->get('/super-admin/financial-summary?from=2030-01-01&to=2030-01-31')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('super-admin/financial-summary')
            ->where('summary.collections.total', 20000)
            ->where('summary.collections.by_type.registration', 20000)
            ->where('summary.collections.by_mode.cash', 20000)
            ->where('summary.earnings.total', 500)
            ->where('filters.from', '2030-01-01'));

    $this->actingAs(spSuperAdmin())
        ->get('/super-admin/financial-summary?from=2030-02-01&to=2030-01-01')
        ->assertSessionHasErrors('to');
});

test('the Financial Summary separates the metal-rate effect on fixed-weight jewellery (29-09-2026)', function () {
    MetalRate::create(['metal' => 'gold', 'rate_per_gram' => 6500, 'effective_from' => now()->toDateString(), 'created_by' => spSuperAdmin()->id]);
    $summary = app(CompanyFinancialSummary::class);
    $before = $summary->build(null, null)['estimate'];

    // 10 g gold booked at ₹6,000/g, today ₹6,500/g → ₹5,000 of the jewellery cost is only the rate change.
    $member = spMember('SP-RATEFX');
    EmiSchedule::create([
        'member_id' => $member->id, 'membership_plan_id' => MembershipPlan::where('code', 'D')->value('id'), 'total_installments' => 10,
        'rate_booking_method' => 'current_rate', 'installment_amount' => 6000, 'rate_per_gram_at_booking' => 6000, 'fixed_weight_grams' => 10,
    ]);
    $after = $summary->build(null, null)['estimate'];

    expect(round($after['rate_impact'] - $before['rate_impact'], 2))->toBe(5000.0);
    expect(round($after['jewellery_cost'] - $before['jewellery_cost'], 2))->toBe(65000.0);
    expect(round($after['surplus_at_booking_rates'] - $after['surplus'], 2))->toBe(round($after['rate_impact'], 2));
});

test('a super admin can top up the Company Wallet, which can never go below zero (T-163)', function () {
    $wallet = app(CompanyWalletService::class);
    $before = (float) $wallet->wallet()->balance;

    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/company-wallet/top-up', ['amount' => 5000, 'description' => 'Prize fund'])
        ->assertRedirect('/super-admin/company-wallet');

    expect((float) $wallet->wallet()->fresh()->balance)->toBe($before + 5000);
    $entry = CompanyWalletLedgerEntry::latest('id')->firstOrFail();
    expect($entry->category)->toBe('manual_topup')
        ->and($entry->entry_type)->toBe('credit')
        ->and($entry->description)->toContain('Prize fund');

    // Top-ups need a positive amount; the description is optional.
    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/company-wallet/top-up', ['amount' => 0])
        ->assertSessionHasErrors('amount');

    // Any spend larger than the balance is refused and changes nothing.
    expect(fn () => $wallet->debit('test_spend', $before + 5000.01, null, 'too much'))
        ->toThrow(ValidationException::class);
    expect((float) $wallet->wallet()->fresh()->balance)->toBe($before + 5000);

    $wallet->debit('test_spend', 5000, null, 'within balance');
    expect((float) $wallet->wallet()->fresh()->balance)->toBe($before);
});

test('Super Admin delivers plan jewellery directly after the last EMI, at the locked booking rate, with hallmarks and no income (T-171)', function () {
    MetalRate::create(['metal' => 'silver', 'rate_per_gram' => 350, 'making_charge_percent' => 10, 'effective_from' => now()->toDateString(), 'created_by' => spSuperAdmin()->id]);
    RuleValue::where('key', 'store_gst_percent')->update(['value' => 3]);

    $plan = MembershipPlan::where('code', 'A')->firstOrFail(); // 100 g silver, 20 EMIs
    $member = spMember('SP-CDL');
    $member->update(['membership_plan_id' => $plan->id]);
    $schedule = EmiSchedule::create([
        'member_id' => $member->id, 'membership_plan_id' => $plan->id, 'total_installments' => 2,
        'rate_booking_method' => 'current_rate', 'installment_amount' => 1000, 'rate_per_gram_at_booking' => 300, 'fixed_weight_grams' => 100,
    ]);
    EmiInstallment::create(['emi_schedule_id' => $schedule->id, 'installment_no' => 1, 'due_date' => now()->toDateString(), 'amount' => 1000, 'status' => 'paid']);
    $second = EmiInstallment::create(['emi_schedule_id' => $schedule->id, 'installment_no' => 2, 'due_date' => now()->toDateString(), 'amount' => 1000, 'status' => 'due']);

    // Before the last EMI: blocked.
    $this->actingAs(spSuperAdmin())
        ->get('/super-admin/company-deliveries?customer_id=SP-CDL')
        ->assertInertia(fn ($page) => $page
            ->component('super-admin/company-deliveries')
            ->where('lookup.rate_is_locked', true)
            ->where('lookup.blocked_reason', 'Delivered after the last EMI — 1 EMI(s) still to pay.'));
    $payload = [
        'customer_id' => 'SP-CDL', 'item_name' => 'Silver anklet', 'item_weight' => 50, 'quantity' => 2,
        'hallmarked' => true, 'hallmarks' => [['huid' => 'HU1A2B', 'charge' => 45], ['huid' => 'HU3C4D', 'charge' => 45]],
    ];
    $this->actingAs(spSuperAdmin())->post('/super-admin/company-deliveries', $payload)->assertSessionHasErrors('customer_id');

    $second->update(['status' => 'paid']);
    $walletBefore = (float) $member->fresh()->wallet_balance;

    $this->actingAs(spSuperAdmin())
        ->followingRedirects()
        ->post('/super-admin/company-deliveries', $payload)
        ->assertInertia(fn ($page) => $page
            ->component('invoices/show')
            ->where('invoice.copy', 'original')
            ->where('invoice.transaction_type', 'company_delivery')
            ->has('invoice.hallmarks', 2));

    // 100 g × locked ₹300 = 30,000 + today's 10% making 3,000 + hallmark 90 = 33,090; GST 3% 992.70 → 34,082.70.
    $delivery = CompanyDelivery::where('member_id', $member->id)->firstOrFail();
    expect((float) $delivery->rate)->toBe(300.0)
        ->and((float) $delivery->metal_value)->toBe(30000.0)
        ->and((float) $delivery->making_charges)->toBe(3000.0)
        ->and((float) $delivery->hallmark_charges)->toBe(90.0)
        ->and((float) $delivery->sale_amount)->toBe(33090.0)
        ->and((float) $delivery->gst_amount)->toBe(992.7)
        ->and((float) $delivery->total_invoice_amount)->toBe(34082.7)
        ->and($delivery->invoice->invoice_no)->toContain('GWD-');

    // The entitlement was created on delivery and is now delivered; no income anywhere.
    expect($member->productBenefits()->whereNotNull('delivered_at')->count())->toBe(1);
    expect((float) $member->fresh()->wallet_balance)->toBe($walletBefore);
    expect(IncomeLedgerCalculation::where('beneficiary_member_id', $member->id)->where('type', 'purchase_repurchase')->exists())->toBeFalse();

    // Later views are duplicates; a second delivery is refused.
    $this->actingAs(spSuperAdmin())
        ->get("/super-admin/company-deliveries/{$delivery->id}/invoice")
        ->assertInertia(fn ($page) => $page->where('invoice.copy', 'duplicate'));
    $this->actingAs(spSuperAdmin())->post('/super-admin/company-deliveries', $payload)->assertSessionHasErrors('customer_id');
});

test('four roles: a company Admin gets every company page except the Super-Admin-only ones, and the company alerts (T-173)', function () {
    // Only a Super Admin can create a company Admin.
    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/company-admins', ['name' => 'Ops Admin', 'email' => 'ops@goldwave.test', 'mobile' => '9876500001', 'password' => 'secret-pass-1'])
        ->assertRedirect('/super-admin/admin-users');
    $admin = User::where('email', 'ops@goldwave.test')->firstOrFail();
    expect($admin->role)->toBe('admin');

    $this->actingAs($admin)
        ->post('/super-admin/company-admins', ['name' => 'X', 'email' => 'x@goldwave.test', 'password' => 'secret-pass-2'])
        ->assertForbidden();

    // Shared company pages, including the company dashboard.
    foreach (['/super-admin/members', '/super-admin/payout-requests', '/super-admin/store-management', '/super-admin/metal-rates', '/super-admin/admin-users'] as $url) {
        $this->actingAs($admin)->get($url)->assertOk();
    }
    $this->actingAs($admin)->get('/dashboard')->assertInertia(fn ($page) => $page->component('super-admin/dashboard'));

    // Super-Admin-only pages.
    foreach (['/super-admin/financial-summary', '/super-admin/rule-versions', '/super-admin/dummy-entry-settings', '/super-admin/dummy-entry-assignment'] as $url) {
        $this->actingAs($admin)->get($url)->assertForbidden();
    }

    // Company alerts reach the Admin too.
    Notifier::toSuperAdmins(new PayoutRequestSubmitted(Model::unguarded(fn () => new PayoutRequest(['requested_amount' => 100]))->setRelation('member', spMember('SP-T173'))));
    expect($admin->notifications()->count())->toBe(1);

    // A Store Admin (Store Owner) never reaches the company portal.
    $storeAdmin = User::factory()->create(['role' => 'store_admin']);
    $this->actingAs($storeAdmin)->get('/super-admin/members')->assertForbidden();
});

test('a super admin can find and promote an existing member to admin (S02)', function () {
    $member = spMember('SP-PROMOTE');

    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/admin-users/find-member', ['customer_id' => 'SP-PROMOTE'])
        ->assertOk()
        ->assertJson(['valid' => true, 'member_customer_id' => 'SP-PROMOTE']);

    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/admin-users', ['customer_id' => 'SP-PROMOTE'])
        ->assertRedirect('/super-admin/admin-users');

    expect($member->user->fresh()->role)->toBe('store_admin');
});

test('promoting a dummy or already-admin customer ID is rejected (S02)', function () {
    $dummy = spMember('SP-DUMMY-2');
    $dummy->update(['is_company_dummy' => true]);

    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/admin-users/find-member', ['customer_id' => 'SP-DUMMY-2'])
        ->assertStatus(422)
        ->assertJson(['valid' => false]);

    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/admin-users', ['customer_id' => 'SP-DUMMY-2'])
        ->assertSessionHasErrors('customer_id');
});

test('a super admin can publish a compensation rule version (S03)', function () {
    $this->actingAs(spSuperAdmin())
        ->withSession(spRecentlyConfirmedPassword())
        ->post('/super-admin/rule-versions', [
            'item_buyback_percent' => 58,
            'notes' => 'Tuned per client feedback.',
        ])
        ->assertRedirect('/super-admin/rule-versions');

    $active = RuleVersion::where('is_active', true)->firstOrFail();
    expect((float) $active->values()->where('key', 'item_buyback_percent')->value('value'))->toBe(58.0);
});

test('Version History shows what actually changed, not just the optional note (T-108)', function () {
    $this->actingAs(spSuperAdmin())
        ->withSession(spRecentlyConfirmedPassword())
        ->post('/super-admin/rule-versions', ['item_buyback_percent' => 58]);

    $this->actingAs(spSuperAdmin())
        ->withSession(spRecentlyConfirmedPassword())
        ->get('/super-admin/rule-versions')
        ->assertInertia(fn ($page) => $page
            ->component('super-admin/rule-versions')
            ->where('versions.0.changes', ['Item Buyback % (Silver): 60 → 58'])
            ->where('versions.1.changes', null));
});

test('the Rule Versions page exposes both Silver and Gold rate tables (T-110)', function () {
    $this->actingAs(spSuperAdmin())
        ->withSession(spRecentlyConfirmedPassword())
        ->get('/super-admin/rule-versions')
        ->assertInertia(fn ($page) => $page
            ->component('super-admin/rule-versions')
            ->has('current.level_income_rates')
            ->has('current.level_income_rates_gold')
            ->has('current.purchase_repurchase_income_rates_gold')
            ->has('current.store_profit_distribution_rates_gold')
            ->where('current.item_buyback_percent_gold', fn ($value) => $value !== null));
});

test('publishing only the Gold tab leaves Silver values untouched (T-110)', function () {
    $this->actingAs(spSuperAdmin())
        ->withSession(spRecentlyConfirmedPassword())
        ->post('/super-admin/rule-versions', [
            'item_buyback_percent_gold' => 55,
            'notes' => 'Gold buyback % tuned.',
        ])
        ->assertRedirect('/super-admin/rule-versions');

    $active = RuleVersion::where('is_active', true)->firstOrFail();
    expect((float) $active->values()->where('key', 'item_buyback_percent_gold')->value('value'))->toBe(55.0);
    expect((float) $active->values()->where('key', 'item_buyback_percent')->value('value'))->toBe(60.0);
});

test('a super admin can update the public landing page hero copy (T-115)', function () {
    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/landing-hero', [
            'headline' => 'New headline for testing.',
            'subtext' => 'New subtext for testing.',
            'cta_primary_label' => 'Sign Up',
            'cta_secondary_label' => 'Log In',
        ])
        ->assertRedirect('/super-admin/landing-hero');

    expect(app(RuleVersionService::class)->value('landing_hero_headline'))->toBe('New headline for testing.');

    $this->get('/')->assertInertia(fn ($page) => $page
        ->component('welcome')
        ->where('hero.headline', 'New headline for testing.')
        ->where('hero.cta_primary_label', 'Sign Up'));
});

test('a super admin can update dummy entry settings and trigger generation (S04)', function () {
    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/dummy-entry-settings', ['enabled' => true, 'daily_count' => 2, 'plan_code' => 'A'])
        ->assertRedirect('/super-admin/dummy-entry-settings');

    expect((int) app(RuleVersionService::class)->value('dummy_entry_daily_count'))->toBe(2);
    expect((string) app(RuleVersionService::class)->value('dummy_entry_plan_code'))->toBe('A');

    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/dummy-entry-settings/generate')
        ->assertRedirect('/super-admin/dummy-entry-settings');

    expect(Member::where('is_company_dummy', true)->where('is_company_root', false)->count())->toBe(2);
});

test('a dummy entry (T-149) is created on the configured EMI plan with a paid, silent installment #1, and generates no compensation', function () {
    Event::fake([PaymentConfirmed::class]);
    RuleValue::where('key', 'dummy_entry_enabled')->update(['value' => true]);
    RuleValue::where('key', 'dummy_entry_daily_count')->update(['value' => 1]);
    RuleValue::where('key', 'dummy_entry_plan_code')->update(['value' => 'A']);

    $dummy = app(GenerateDailyDummyEntries::class)()[0];

    expect($dummy->membership_plan_id)->toBe(MembershipPlan::where('code', 'A')->value('id'));
    $schedule = $dummy->emiSchedule;
    expect($schedule->rate_booking_method)->toBe('future_rate');
    expect((float) $schedule->installment_amount)->toBe(1000.0);
    expect($schedule->installments)->toHaveCount(1);

    $installment = $schedule->installments->first();
    expect($installment->status)->toBe('paid');
    expect($installment->payment->mode)->toBe('cash');
    expect($installment->payment->status)->toBe('paid');
    expect($installment->payment->cash_status)->toBe('approved');

    // Never queued for manual cash approval, never dispatches PaymentConfirmed, never triggers real compensation.
    $this->actingAs(spSuperAdmin())->get('/super-admin/cash-payments')
        ->assertInertia(fn ($page) => $page->where('pending', []));
    Event::assertNotDispatched(PaymentConfirmed::class);
    expect(IncomeLedgerCalculation::where('source_payment_id', $installment->payment_id)->count())->toBe(0);
    expect(PairEntry::where('source_payment_id', $installment->payment_id)->count())->toBe(0);
});

test('a super admin can assign a leader to a dummy entry (S05), and the leader\'s own schedule starts fresh at installment #2', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-01'));
    RuleValue::where('key', 'dummy_entry_enabled')->update(['value' => true]);
    RuleValue::where('key', 'dummy_entry_daily_count')->update(['value' => 1]);
    $dummy = app(GenerateDailyDummyEntries::class)()[0];

    // Months pass with the entry sitting unassigned — no next EMI exists or is payable for it.
    Carbon::setTestNow(Carbon::parse('2026-12-15'));
    expect($dummy->emiSchedule->fresh()->installments)->toHaveCount(1);

    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/dummy-entry-assignment', [
            'member_id' => $dummy->id,
            'name' => 'Real Leader',
            'email' => 'realleader@goldwave.test',
            'mobile' => '9998887771',
        ])
        ->assertRedirect('/super-admin/dummy-entry-assignment');

    $leader = $dummy->fresh();
    expect($leader->dummy_status)->toBe('assigned');

    $installments = $leader->emiSchedule->installments()->orderBy('installment_no')->get();
    expect($installments)->toHaveCount(20);
    expect($installments->firstWhere('installment_no', 1)->status)->toBe('paid');

    $second = $installments->firstWhere('installment_no', 2);
    expect($second->status)->toBe('due'); // due "today" (the assignment date) — no grace period, no catch-up for the elapsed months.
    expect($second->due_date->toDateString())->toBe('2026-12-15');

    $third = $installments->firstWhere('installment_no', 3);
    expect($third->status)->toBe('upcoming');
    expect($third->due_date->toDateString())->toBe('2027-01-15');

    Carbon::setTestNow();
});

test('a super admin can update draw settings and reconcile an executed draw (S06)', function () {
    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/draw-settings', [
            'draw_group_size' => 5,
            'draw_prize_silver_name' => 'Silver Anklet',
            'draw_prize_silver_value' => 18000,
            'draw_prize_gold_name' => 'Gold Chain',
            'draw_prize_gold_value' => 26000,
            'draw_winners_per_month' => 1,
        ])
        ->assertRedirect('/super-admin/draw-settings');

    expect((int) app(RuleVersionService::class)->value('draw_group_size'))->toBe(5)
        ->and(app(RuleVersionService::class)->value('draw_prize_silver_name'))->toBe('Silver Anklet')
        ->and((float) app(RuleVersionService::class)->value('draw_prize_gold_value'))->toBe(26000.0);

    for ($i = 1; $i <= 5; $i++) {
        spMember(sprintf('SPDRAW%03d', $i));
    }
    app(GenerateDrawGroups::class)();
    // No prize set for the group — the draw still runs, with the Silver default just saved above.
    $execution = app(ExecuteMonthlyDraw::class)()[0];

    $this->actingAs(spSuperAdmin())
        ->get('/super-admin/draw-management')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('super-admin/draw-management')
            // 30-09-2026 — each group card shows its Customer ID range and how many are still in the draw.
            ->where('groups.0.first_customer_id', 'SPDRAW001')
            ->where('groups.0.last_customer_id', 'SPDRAW005')
            ->where('groups.0.winners', 1)
            ->where('groups.0.eligible_remaining', 4)
            ->where('groups.0.draws_done', 1)
            ->where('groups.0.executions.0.prize_name', 'Silver Anklet')
            ->where('groups.0.executions.0.prize_value', '18000.00')
            ->where('groups.0.next_draw.cycle_month_no', 2)
            ->where('groups.0.next_draw.prize_name', 'Silver Anklet')
            ->where('pool.group_size', 5)
            ->where('pool.grouped', 5));

    // A group's own prize for a month not drawn yet; a month already drawn is refused.
    $group = $execution->drawGroup;
    $this->actingAs(spSuperAdmin())
        ->post("/super-admin/draw-management/groups/{$group->id}/month-prize", ['cycle_month_no' => 2, 'prize_name' => 'Silver Bangle', 'prize_value' => 22000, 'winners_count' => 2])
        ->assertRedirect('/super-admin/draw-management');
    expect(DrawGroupMonthConfig::where('draw_group_id', $group->id)->where('cycle_month_no', 2)->sole())
        ->prize_name->toBe('Silver Bangle')
        ->winners_count->toBe(2)
        ->metal_type->toBe('silver');
    $this->actingAs(spSuperAdmin())
        ->post("/super-admin/draw-management/groups/{$group->id}/month-prize", ['cycle_month_no' => 1, 'prize_name' => 'X', 'prize_value' => 1, 'winners_count' => 1])
        ->assertSessionHasErrors('cycle_month_no');

    $this->actingAs(spSuperAdmin())
        ->post("/super-admin/draw-management/{$execution->id}/reconcile", ['correction_note' => 'Verified in person.'])
        ->assertRedirect('/super-admin/draw-management');

    expect($execution->fresh()->status)->toBe('reconciled');
});

test('a super admin records a metal rate per 10 gm with a making-charges %, stored per gram (S07, T-165)', function () {
    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/metal-rates', [
            'metal' => 'gold',
            'rate_per_10_grams' => 65000.5,
            'making_charge_percent' => 12,
            'effective_from' => now()->toDateString(),
        ])
        ->assertRedirect('/super-admin/metal-rates');

    $rate = MetalRate::where('metal', 'gold')->latest('id')->firstOrFail();
    expect((float) $rate->rate_per_gram)->toBe(6500.05)
        ->and((float) $rate->making_charge_percent)->toBe(12.0);

    // More than one decimal per 10 gm would not be exact per gram, so it is refused.
    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/metal-rates', [
            'metal' => 'silver',
            'rate_per_10_grams' => 2300.55,
            'making_charge_percent' => 8,
            'effective_from' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('rate_per_10_grams');
});

test('a super admin can update payout and TDS settings (S08)', function () {
    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/payout-tds-settings', [
            'payout_min_amount' => 1000,
            'payout_tds_percent' => 5,
            'payout_processing_fee_percent' => 1,
        ])
        ->assertRedirect('/super-admin/payout-tds-settings');

    expect((float) app(RuleVersionService::class)->value('payout_min_amount'))->toBe(1000.0);
});

test('a super admin can create a store, reassign its owner, and change its status (S09)', function () {
    $owner = User::factory()->create(['role' => 'store_admin']);

    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/store-management', [
            'name' => 'S09 Test Store',
            'owner_user_id' => $owner->id,
            'jewellery_allocation_value' => 100000,
            'advance_amount' => 20000,
            'password_mode' => 'manual',
            'password' => 'InitialPass123!',
        ])
        ->assertRedirect('/super-admin/store-management');

    $store = Store::where('name', 'S09 Test Store')->firstOrFail();
    expect($store->owner_user_id)->toBe($owner->id);
    expect($store->store_code)->not->toBeNull();
    expect(Hash::check('InitialPass123!', $store->password))->toBeTrue();

    $newOwner = User::factory()->create(['role' => 'store_admin']);

    $this->actingAs(spSuperAdmin())
        ->post("/super-admin/store-management/{$store->id}/reassign-owner", [
            'owner_user_id' => $newOwner->id,
            'current_password' => 'password',
        ])
        ->assertRedirect("/super-admin/store-management/{$store->id}");

    $reassigned = $store->fresh();
    expect($reassigned->owner_user_id)->toBe($newOwner->id);
    expect(Hash::check('InitialPass123!', $reassigned->password))->toBeFalse();

    $this->actingAs(spSuperAdmin())
        ->post("/super-admin/store-management/{$store->id}/reset-password", ['password_mode' => 'auto'])
        ->assertRedirect("/super-admin/store-management/{$store->id}");

    expect(Hash::check('ReassignedPass456!', $store->fresh()->password))->toBeFalse();

    $this->actingAs(spSuperAdmin())
        ->post("/super-admin/store-management/{$store->id}/status", ['status' => 'inactive'])
        ->assertRedirect("/super-admin/store-management/{$store->id}");

    expect($store->fresh()->status)->toBe('inactive');

    $this->actingAs(spSuperAdmin())
        ->get("/super-admin/store-management/{$store->id}")
        ->assertOk();
});

test('a super admin can allocate item-wise jewellery inventory to a store, and re-adding the same item increases its quantity (§16.5)', function () {
    $owner = User::factory()->create(['role' => 'store_admin']);
    $store = app(CreateStore::class)('Inventory Test Store', $owner, null, null, 100000, 20000, spSuperAdmin(), 'InitialPass123!');
    $url = "/super-admin/store-management/{$store->id}/inventory";

    $this->actingAs(spSuperAdmin())
        ->post($url, [
            'item_name' => 'Gold Bangle',
            'metal' => 'gold',
            'weight' => 10.5,
            'quantity' => 4,
            'price' => 65000,
            'description' => '22K',
        ])
        ->assertRedirect("/super-admin/store-management/{$store->id}");

    $item = $store->inventoryItems()->where('item_name', 'Gold Bangle')->firstOrFail();
    expect($item->quantity)->toBe(4);
    expect((float) $item->weight)->toBe(10.5);
    // T-169 — the typed price is ignored: 10.5 g × today's seeded gold rate ₹6,000/g.
    expect((float) $item->price)->toBe(63000.0);
    expect($item->description)->toBe('22K');

    // Same item/metal/weight/price again -> quantity increases, no duplicate row.
    $this->actingAs(spSuperAdmin())
        ->post($url, ['item_name' => 'Gold Bangle', 'metal' => 'gold', 'weight' => 10.5, 'quantity' => 6, 'price' => 65000]);

    expect($store->inventoryItems()->where('item_name', 'Gold Bangle')->count())->toBe(1);
    expect($item->fresh()->quantity)->toBe(10);

    // A different item/metal creates its own row.
    $this->actingAs(spSuperAdmin())
        ->post($url, ['item_name' => 'Silver Necklace', 'metal' => 'silver', 'weight' => 25, 'quantity' => 5, 'price' => 17500]);

    expect($store->inventoryItems()->count())->toBe(2);

    $this->actingAs(spSuperAdmin())
        ->get("/super-admin/store-management/{$store->id}")
        ->assertInertia(fn ($page) => $page
            ->component('super-admin/store-detail')
            ->has('inventory', 2)
            ->where('inventory.0.item_name', 'Gold Bangle')
            ->where('inventory.0.quantity', 10));

    $this->actingAs(spSuperAdmin())
        ->post($url, ['item_name' => '', 'metal' => 'bronze', 'weight' => 0, 'quantity' => 0, 'price' => -1])
        ->assertSessionHasErrors(['item_name', 'metal', 'weight', 'quantity']);

    $member = spMember('SP-INV-MEMBER');
    $this->actingAs($member->user)
        ->post($url, ['item_name' => 'X', 'metal' => 'gold', 'weight' => 1, 'quantity' => 1, 'price' => 1])
        ->assertForbidden();
});

test('reassigning a store owner requires the Super Admin\'s own password and auto-generates the new Store password (T-133)', function () {
    $owner = User::factory()->create(['role' => 'store_admin']);
    $store = app(CreateStore::class)('T133 Store', $owner, null, null, 100000, 20000, spSuperAdmin(), 'InitialPass123!');
    $newOwner = User::factory()->create(['role' => 'store_admin']);
    $url = "/super-admin/store-management/{$store->id}/reassign-owner";

    $this->actingAs(spSuperAdmin())
        ->post($url, ['owner_user_id' => $newOwner->id])
        ->assertSessionHasErrors('current_password');

    $this->actingAs(spSuperAdmin())
        ->post($url, ['owner_user_id' => $newOwner->id, 'current_password' => 'wrong-password'])
        ->assertSessionHasErrors('current_password');

    expect($store->fresh()->owner_user_id)->toBe($owner->id);

    $this->actingAs(spSuperAdmin())
        ->post($url, ['owner_user_id' => $newOwner->id, 'current_password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect("/super-admin/store-management/{$store->id}")
        ->assertSessionHas('status', fn (string $status) => str_contains($status, 'New Store password: '));

    $reassigned = $store->fresh();
    expect($reassigned->owner_user_id)->toBe($newOwner->id);
    expect(Hash::check('InitialPass123!', $reassigned->password))->toBeFalse();
});

test('the Rule Versions page and its publish endpoint require a recent password confirmation (T-132)', function () {
    $this->actingAs(spSuperAdmin())
        ->get('/super-admin/rule-versions')
        ->assertRedirect(route('password.confirm'));

    $this->actingAs(spSuperAdmin())
        ->post('/super-admin/rule-versions', ['item_buyback_percent' => 58])
        ->assertRedirect(route('password.confirm'));

    expect((float) app(RuleVersionService::class)->value('item_buyback_percent'))->toBe(60.0);

    $this->actingAs(spSuperAdmin())
        ->withSession(spRecentlyConfirmedPassword())
        ->get('/super-admin/rule-versions')
        ->assertOk();
});

test('a wrong password does not unlock the Rule Versions page, a correct one does (T-132)', function () {
    $this->actingAs(spSuperAdmin())
        ->post(route('password.confirm.store'), ['password' => 'not-the-password'])
        ->assertSessionHasErrors('password');

    $this->actingAs(spSuperAdmin())
        ->get('/super-admin/rule-versions')
        ->assertRedirect(route('password.confirm'));

    $this->actingAs(spSuperAdmin())
        ->post(route('password.confirm.store'), ['password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->get('/super-admin/rule-versions')->assertOk();
});

test('the Rule Versions password confirmation expires after 5 minutes (T-132)', function () {
    $this->actingAs(spSuperAdmin())
        ->withSession(['auth.password_confirmed_at' => time() - 240])
        ->get('/super-admin/rule-versions')
        ->assertOk();

    $this->actingAs(spSuperAdmin())
        ->withSession(['auth.password_confirmed_at' => time() - 301])
        ->get('/super-admin/rule-versions')
        ->assertRedirect(route('password.confirm'));
});

test('a non-super-admin still gets 403 on the Rule Versions page even with a fresh confirmation (T-132)', function () {
    $member = spMember('SP-T132-MEMBER');

    $this->actingAs($member->user)
        ->withSession(spRecentlyConfirmedPassword())
        ->get('/super-admin/rule-versions')
        ->assertForbidden();
});

test('a super admin can top up a store wallet (S10)', function () {
    $store = app(CreateStore::class)('S10 Test Store', null, null, null, 50000, 0, spSuperAdmin());

    $this->actingAs(spSuperAdmin())
        ->post("/super-admin/store-wallets/{$store->id}/topup", ['amount' => 15000, 'description' => 'Owner top-up.'])
        ->assertRedirect("/super-admin/store-wallets/{$store->id}");

    expect((float) $store->wallet->fresh()->balance)->toBe(15000.0);

    $this->actingAs(spSuperAdmin())
        ->get('/super-admin/store-wallets')
        ->assertOk();
});

test('a super admin can view the member list, a member detail page, and export members (Admin Member Management)', function () {
    $member = spMember('SPMEMLIST');

    $this->actingAs(spSuperAdmin())
        ->get('/super-admin/members')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('super-admin/member-management'));

    $this->actingAs(spSuperAdmin())
        ->get("/super-admin/members/{$member->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('super-admin/member-detail')
            ->where('member.customer_id', 'SPMEMLIST'));

    $response = $this->actingAs(spSuperAdmin())->get('/super-admin/members/export');
    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

test('a super admin can directly edit a member\'s details, including a new bank account (T-106)', function () {
    $member = spMember('SPMEMEDIT');

    $this->actingAs(spSuperAdmin())
        ->patch("/super-admin/members/{$member->id}", [
            'name' => 'Corrected Name',
            'email' => 'corrected@goldwave.test',
            'mobile' => '9998887776',
            'pan_card' => 'ABCDE1234F',
            'aadhaar_card' => '123456789012',
            'address' => '221B Baker Street',
            'bank_account_holder_name' => 'Corrected Name',
            'bank_account_number' => '000111222333',
            'bank_ifsc_code' => 'HDFC0001234',
            'bank_name' => 'HDFC Bank',
        ])
        ->assertRedirect("/super-admin/members/{$member->id}");

    $member->refresh();
    expect($member->user->name)->toBe('Corrected Name');
    expect($member->user->email)->toBe('corrected@goldwave.test');
    expect($member->pan_card)->toBe('ABCDE1234F');
    expect($member->pending_fields_submitted_at)->not->toBeNull();

    $bankDetail = $member->bankDetails()->latest('id')->firstOrFail();
    expect($bankDetail->account_number)->toBe('000111222333');
    expect($bankDetail->verified_at)->toBeNull();
});

test('a super admin can directly reset a member\'s password (T-111)', function () {
    $member = spMember('SPMEMPWRESET');
    $userId = $member->user->id;

    $this->actingAs(spSuperAdmin())
        ->patch("/super-admin/members/{$member->id}/reset-password", [
            'password' => 'BrandNewPassword123!',
            'password_confirmation' => 'BrandNewPassword123!',
        ])
        ->assertRedirect("/super-admin/members/{$member->id}");

    $this->assertTrue(Hash::check('BrandNewPassword123!', User::findOrFail($userId)->password));
});

test('resetting a member\'s password rejects a mismatched confirmation', function () {
    $member = spMember('SPMEMPWMISMATCH');

    $this->actingAs(spSuperAdmin())
        ->patch("/super-admin/members/{$member->id}/reset-password", [
            'password' => 'BrandNewPassword123!',
            'password_confirmation' => 'SomethingElse123!',
        ])
        ->assertSessionHasErrors('password');
});

test('re-submitting a member\'s unchanged bank details does not reset an already-verified account', function () {
    $member = spMember('SPMEMBANKOK');
    $bankDetail = MemberBankDetail::create([
        'member_id' => $member->id,
        'account_holder_name' => 'Same Name',
        'account_number' => '555666777',
        'ifsc_code' => 'ICIC0009999',
        'bank_name' => 'ICICI Bank',
        'verified_by' => spSuperAdmin()->id,
        'verified_at' => now(),
    ]);

    $this->actingAs(spSuperAdmin())
        ->patch("/super-admin/members/{$member->id}", [
            'name' => $member->user->name,
            'email' => $member->user->email,
            'bank_account_holder_name' => 'Same Name',
            'bank_account_number' => '555666777',
            'bank_ifsc_code' => 'ICIC0009999',
            'bank_name' => 'ICICI Bank',
        ])
        ->assertRedirect("/super-admin/members/{$member->id}");

    expect($bankDetail->fresh()->verified_at)->not->toBeNull();
});

test('a super admin can edit an admin user\'s own details (T-106)', function () {
    $admin = User::factory()->create(['role' => 'store_admin', 'email' => 'oldadminemail@goldwave.test']);

    $this->actingAs(spSuperAdmin())
        ->patch("/super-admin/admin-users/{$admin->id}", [
            'name' => 'Renamed Admin',
            'email' => 'newadminemail@goldwave.test',
            'mobile' => '9123456780',
        ])
        ->assertRedirect('/super-admin/admin-users');

    expect($admin->fresh()->name)->toBe('Renamed Admin');
    expect($admin->fresh()->email)->toBe('newadminemail@goldwave.test');
});

test('a super admin can view the compensation audit page, and the config page redirects to rule versions (Admin Compensation Management)', function () {
    $member = spMember('SPCOMPAUDIT');
    IncomeLedgerCalculation::create([
        'type' => 'level_income',
        'beneficiary_member_id' => $member->id,
        'level_no' => 1,
        'rate_percent' => 5,
        'amount' => 500,
        'rule_version_id' => RuleVersion::where('is_active', true)->firstOrFail()->id,
        'eligibility_status' => 'paid',
    ]);

    $this->actingAs(spSuperAdmin())
        ->get('/super-admin/compensation/audit')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('super-admin/compensation-audit'));

    $this->actingAs(spSuperAdmin())
        ->get('/super-admin/compensation/config')
        ->assertRedirect('/super-admin/rule-versions');
});

test('a super admin can view and process a pending payout request (T-109)', function () {
    $member = spMember('SPPAYOUT1');
    app(WalletLedgerService::class)->credit($member, 'level_income', 20000, null, 'seed credit');
    $bankDetail = MemberBankDetail::create([
        'member_id' => $member->id,
        'account_holder_name' => 'SP Payout Holder',
        'account_number' => '1112223334',
        'ifsc_code' => 'TEST0009999',
        'bank_name' => 'Test Bank',
        'verified_at' => now(),
    ]);
    $payoutRequest = app(SubmitPayoutRequest::class)($member->fresh(), 10000, $bankDetail);

    $this->actingAs(spSuperAdmin())
        ->get('/super-admin/payout-requests')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('super-admin/payout-requests')
            ->where('pending.0.member.customer_id', 'SPPAYOUT1'));

    $this->actingAs(spSuperAdmin())
        ->post("/super-admin/payout-requests/{$payoutRequest->id}/process", [
            'method' => 'bank_transfer',
            'reference' => 'UTR123',
        ])
        ->assertRedirect();

    expect($payoutRequest->fresh()->status)->toBe('processed');
});

test('a processed payout moves from the queue into Payout History with its transaction, and reads as Paid in the member ledger (28-09-2026)', function () {
    $member = spMember('SPPAYOUTHIST');
    app(WalletLedgerService::class)->credit($member, 'level_income', 20000, null, 'seed credit');
    $bankDetail = MemberBankDetail::create([
        'member_id' => $member->id,
        'account_holder_name' => 'SP History Holder',
        'account_number' => '001122330036',
        'ifsc_code' => 'TEST0007777',
        'bank_name' => 'HDFC Bank',
        'verified_at' => now(),
    ]);
    $payoutRequest = app(SubmitPayoutRequest::class)($member->fresh(), 20000, $bankDetail);

    $this->actingAs($member->user)
        ->get('/member/wallet')
        ->assertInertia(fn ($page) => $page
            ->where('entries.data.0.status_label', 'on hold')
            ->where('entries.data.0.description', "Payout request #{$payoutRequest->id} — on hold, awaiting processing"));

    $this->actingAs(spSuperAdmin())
        ->post("/super-admin/payout-requests/{$payoutRequest->id}/process", [
            'method' => 'bank_transfer',
            'reference' => 'UTR777',
        ])
        ->assertRedirect();

    $this->actingAs(spSuperAdmin())
        ->get('/super-admin/payout-requests')
        ->assertInertia(fn ($page) => $page
            ->where('pending', [])
            ->where('history.0.id', $payoutRequest->id)
            ->where('history.0.status', 'processed')
            ->where('history.0.member.customer_id', 'SPPAYOUTHIST')
            ->where('history.0.transaction.method', 'Bank Transfer')
            ->where('history.0.transaction.reference', 'UTR777')
            ->where('history.0.transaction.net_amount', '20000.00'));

    $this->actingAs($member->user)
        ->get('/member/wallet')
        ->assertInertia(fn ($page) => $page
            ->where('entries.data.0.status', 'confirmed')
            ->where('entries.data.0.status_label', 'paid')
            ->where('entries.data.0.description', "Payout #{$payoutRequest->id} paid via Bank Transfer to HDFC Bank ••0036 (ref UTR777)"));

    // "paid" is what the member sees, so searching for it must find the row.
    $this->actingAs($member->user)
        ->get('/member/wallet?search=paid')
        ->assertInertia(fn ($page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.category', 'payout'));
});

test('a super admin can reject a pending payout request, releasing its hold (T-109)', function () {
    $member = spMember('SPPAYOUT2');
    app(WalletLedgerService::class)->credit($member, 'level_income', 20000, null, 'seed credit');
    $bankDetail = MemberBankDetail::create([
        'member_id' => $member->id,
        'account_holder_name' => 'SP Payout Holder 2',
        'account_number' => '5556667778',
        'ifsc_code' => 'TEST0008888',
        'bank_name' => 'Test Bank',
        'verified_at' => now(),
    ]);
    $payoutRequest = app(SubmitPayoutRequest::class)($member->fresh(), 3000, $bankDetail);

    $this->actingAs(spSuperAdmin())
        ->post("/super-admin/payout-requests/{$payoutRequest->id}/reject")
        ->assertRedirect();

    expect($payoutRequest->fresh()->status)->toBe('rejected');
});

test('a super admin can cancel a pending payout request, releasing its hold (T-147)', function () {
    $member = spMember('SPPAYOUT3');
    app(WalletLedgerService::class)->credit($member, 'level_income', 20000, null, 'seed credit');
    $bankDetail = MemberBankDetail::create([
        'member_id' => $member->id,
        'account_holder_name' => 'SP Payout Holder 3',
        'account_number' => '5556667779',
        'ifsc_code' => 'TEST0008889',
        'bank_name' => 'Test Bank',
        'verified_at' => now(),
    ]);
    $payoutRequest = app(SubmitPayoutRequest::class)($member->fresh(), 3000, $bankDetail);
    expect((float) $member->fresh()->wallet_hold_amount)->toBe(3000.0);

    $this->actingAs(spSuperAdmin())
        ->post("/super-admin/payout-requests/{$payoutRequest->id}/cancel")
        ->assertRedirect();

    expect($payoutRequest->fresh()->status)->toBe('cancelled');
    expect((float) $member->fresh()->wallet_hold_amount)->toBe(0.0);
});

test('a super admin can verify a member\'s bank details, unblocking their payout request (T-146)', function () {
    $member = spMember('SPBANKVERIFY1');
    $bankDetail = MemberBankDetail::create([
        'member_id' => $member->id,
        'account_holder_name' => 'Bank Verify Holder',
        'account_number' => '1112223334',
        'ifsc_code' => 'TEST0007777',
        'bank_name' => 'Test Bank',
    ]);
    expect($bankDetail->verified_at)->toBeNull();

    $this->actingAs(spSuperAdmin())
        ->post("/super-admin/members/{$member->id}/verify-bank-detail")
        ->assertRedirect("/super-admin/members/{$member->id}");

    $verified = $bankDetail->fresh();
    expect($verified->verified_at)->not->toBeNull();
    expect($verified->verified_by)->toBe(spSuperAdmin()->id);

    // Idempotent: verifying again does not overwrite who/when it was first verified.
    $firstVerifiedAt = $verified->verified_at;
    $this->actingAs(spSuperAdmin())->post("/super-admin/members/{$member->id}/verify-bank-detail");
    expect($bankDetail->fresh()->verified_at->equalTo($firstVerifiedAt))->toBeTrue();

    // A member with no bank details submitted yet -> 404, not a crash.
    $noBankMember = spMember('SPBANKVERIFY2');
    $this->actingAs(spSuperAdmin())
        ->post("/super-admin/members/{$noBankMember->id}/verify-bank-detail")
        ->assertNotFound();

    // Non-Super-Admin is forbidden.
    $this->actingAs(spMember('SPBANKVERIFY3')->user)
        ->post("/super-admin/members/{$member->id}/verify-bank-detail")
        ->assertForbidden();
});

test('a super admin can view and approve a pending profile change request (T-109)', function () {
    $member = spMember('SPCHANGEREQ1');
    $member->update(['pending_fields_submitted_at' => now()]);
    $changeRequest = app(SubmitProfileChangeRequest::class)($member->fresh(), 'address', 'New Address, City', 'Moved house');

    $this->actingAs(spSuperAdmin())
        ->get('/super-admin/profile-change-requests')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('super-admin/profile-change-requests')
            ->where('pending.0.member.customer_id', 'SPCHANGEREQ1')
            ->where('pending.0.new_value', 'New Address, City'));

    $this->actingAs(spSuperAdmin())
        ->post("/super-admin/profile-change-requests/{$changeRequest->id}/approve")
        ->assertRedirect();

    expect($changeRequest->fresh()->status)->toBe('approved');
    expect($member->fresh()->address)->toBe('New Address, City');
});

test('a super admin can reject a pending profile change request with a reason, leaving the field untouched (T-109)', function () {
    $member = spMember('SPCHANGEREQ2');
    $member->update(['pending_fields_submitted_at' => now(), 'address' => 'Original Address']);
    $changeRequest = app(SubmitProfileChangeRequest::class)($member->fresh(), 'address', 'Disputed Address');

    $this->actingAs(spSuperAdmin())
        ->post("/super-admin/profile-change-requests/{$changeRequest->id}/reject", [
            'rejection_reason' => 'Could not verify new address.',
        ])
        ->assertRedirect();

    expect($changeRequest->fresh()->status)->toBe('rejected');
    expect($changeRequest->fresh()->rejection_reason)->toBe('Could not verify new address.');
    expect($member->fresh()->address)->toBe('Original Address');
});

test('a super admin can set a member\'s gender without marking Pending Fields as submitted (T-122)', function () {
    $member = spMember('SPMEMGENDER');

    $this->actingAs(spSuperAdmin())
        ->patch("/super-admin/members/{$member->id}", [
            'name' => 'Gender Member',
            'email' => 'gendermember@goldwave.test',
            'gender' => 'female',
        ])
        ->assertRedirect("/super-admin/members/{$member->id}");

    $member->refresh();
    expect($member->gender)->toBe('female');
    expect($member->pending_fields_submitted_at)->toBeNull();

    $this->actingAs(spSuperAdmin())
        ->patch("/super-admin/members/{$member->id}", [
            'name' => 'Gender Member',
            'email' => 'gendermember@goldwave.test',
            'gender' => 'invalid',
        ])
        ->assertSessionHasErrors('gender');
});

test('Super Admin can view the Company Wallet balance and ledger (T-153, DOMAIN_LOGIC.md §12.2(b))', function () {
    app(CompanyWalletService::class)->credit('assisted_registration', 15000, null, 'Test credit');

    $this->actingAs(spSuperAdmin())
        ->get('/super-admin/company-wallet')
        ->assertInertia(fn ($page) => $page
            ->where('balance', '15000.00')
            ->where('entries.0.category', 'assisted_registration'));
});

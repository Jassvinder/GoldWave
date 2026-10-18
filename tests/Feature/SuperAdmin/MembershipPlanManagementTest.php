<?php

use App\Actions\Emi\BookCurrentRate;
use App\Actions\Emi\QuoteCurrentRateBooking;
use App\Actions\Emi\RevertCurrentRateBooking;
use App\Actions\Registration\RegisterMember;
use App\Models\EmiInstallment;
use App\Models\EmiSchedule;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\MembershipPlanChange;
use App\Models\RuleVersion;
use App\Models\User;
use App\Services\RuleVersionService;

/**
 * T-237 (DOMAIN_LOGIC.md §3.3, Docs/TEST.md scenarios 46–47) — Super Admin manages membership plans; a plan edit never
 * changes a member who already joined.
 */
function mpSuperAdmin(): User
{
    return User::factory()->create(['role' => 'super_admin', 'name' => 'Plan Owner']);
}

function mpConfirmed(): array
{
    return ['auth.password_confirmed_at' => time()];
}

/** A member who joined `$code` before any edit, with `$paid` EMIs paid — the schedule as RegisterMember now creates it. */
function mpJoinedMember(string $customerId, string $code, int $paid): Member
{
    $plan = MembershipPlan::where('code', $code)->firstOrFail();
    $member = Member::create([
        'user_id' => User::factory()->create(['role' => 'member'])->id,
        'customer_id' => $customerId,
        'membership_plan_id' => $plan->id,
        'status' => 'active',
        'activated_at' => now(),
    ]);

    $schedule = EmiSchedule::create([
        'member_id' => $member->id,
        'membership_plan_id' => $plan->id,
        'total_installments' => $plan->installment_count,
        'rate_booking_method' => 'future_rate',
        'installment_amount' => $plan->amount,
        'future_commitment_amount' => (float) $plan->amount * $plan->installment_count,
        'plan_installment_amount' => $plan->amount,
        'plan_fixed_weight_grams' => $plan->fixed_weight_grams,
    ]);

    for ($no = 1; $no <= $plan->installment_count; $no++) {
        EmiInstallment::create([
            'emi_schedule_id' => $schedule->id,
            'installment_no' => $no,
            'due_date' => now()->addMonthsNoOverflow($no - 1)->toDateString(),
            'amount' => $plan->amount,
            'status' => $no <= $paid ? 'paid' : ($no === $paid + 1 ? 'due' : 'upcoming'),
        ]);
    }

    return $member->fresh();
}

/** @return array<string, mixed> */
function mpEmiEdit(array $overrides = []): array
{
    return [
        'name' => 'Silver Prime',
        'amount' => 2000,
        'installment_count' => 12,
        'fixed_weight_grams' => 66,
        'pair_emis' => 2,
        'draw_emis' => 2,
        'is_active' => true,
        ...$overrides,
    ];
}

function mpRegister(string $email, string $mobile, int $planId): Member
{
    return app(RegisterMember::class)([
        'sponsor_code' => Member::where('is_company_root', true)->value('customer_id'),
        'placement_side' => 'left',
        'name' => 'New Joiner',
        'gender' => 'male',
        'email' => $email,
        'mobile' => $mobile,
        'membership_plan_id' => $planId,
        'payment_mode' => 'cash',
    ]);
}

beforeEach(function () {
    $this->seed(); // plans A–H, rule version, silver ₹350/g with 0% making
});

test('scenario 46: editing Silver Prime changes nothing for a member who already joined', function () {
    $admin = mpSuperAdmin();
    $plan = MembershipPlan::where('code', 'B')->firstOrFail();
    $member = mpJoinedMember('MP-OLD', 'B', 2);

    $this->actingAs($admin)->withSession(mpConfirmed())
        ->patch(route('super-admin.membership-plans.update', $plan), mpEmiEdit())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('super-admin.membership-plans.index'));

    $plan->refresh();
    expect((float) $plan->amount)->toBe(2000.0)
        ->and($plan->installment_count)->toBe(12)
        ->and((float) $plan->fixed_weight_grams)->toBe(66.0);

    // The member's own EMIs are untouched.
    $schedule = $member->emiSchedule()->firstOrFail();
    $unpaid = $schedule->installments()->where('status', '!=', 'paid')->pluck('amount')->map(fn ($a) => (float) $a);
    expect($schedule->total_installments)->toBe(10)
        ->and($unpaid)->toHaveCount(8)
        ->and($unpaid->unique()->values()->all())->toBe([3000.0]);

    // The member's plan page shows their own figures.
    $this->actingAs($member->user)->get(route('member.membership.show'))
        ->assertInertia(fn ($page) => $page->where('plan.amount', '3000.00')->where('plan.installment_count', 10));

    // Current Rate Booking uses the member's 100gm and 10 EMIs: 35,000 − 6,000 = 29,000 over 8 → 3,625 + 290.
    $quote = app(QuoteCurrentRateBooking::class)($member);
    expect($quote['fixed_weight_grams'])->toBe(100.0)
        ->and($quote['total_value'])->toBe(35000.0)
        ->and($quote['remaining_value'])->toBe(29000.0)
        ->and($quote['pending_installments'])->toBe(8)
        ->and($quote['installment_amount'])->toBe(3915.0);

    // After a booking, a revert goes back to the ₹3,000 the member joined at — not the plan's new ₹2,000.
    app(BookCurrentRate::class)($member, $quote['metal_rate_id'], $quote['paid_installments']);
    app(RevertCurrentRateBooking::class)($member->fresh(), $admin, 'Booked by mistake');

    $schedule->refresh();
    expect((float) $schedule->installment_amount)->toBe(3000.0)
        ->and($schedule->installments()->where('status', '!=', 'paid')->pluck('amount')->map(fn ($a) => (float) $a)->unique()->values()->all())->toBe([3000.0]);

    // A new member gets the new price and weight.
    $new = mpRegister('mp-new@example.test', '9876500001', $plan->id);
    $newSchedule = $new->emiSchedule()->firstOrFail();
    expect((float) $newSchedule->installment_amount)->toBe(2000.0)
        ->and($newSchedule->total_installments)->toBe(12)
        ->and((float) $newSchedule->plan_installment_amount)->toBe(2000.0)
        ->and((float) $newSchedule->plan_fixed_weight_grams)->toBe(66.0);

    // The change is logged with before and after.
    $change = MembershipPlanChange::sole();
    expect($change->action)->toBe('updated')
        ->and($change->changed_by)->toBe($admin->id)
        ->and($change->before['amount'])->toEqual(3000)
        ->and($change->before['installment_count'])->toBe(10)
        ->and($change->before['fixed_weight_grams'])->toEqual(100)
        ->and($change->after['amount'])->toEqual(2000)
        ->and($change->after['installment_count'])->toBe(12)
        ->and($change->after['fixed_weight_grams'])->toEqual(66);
});

test('scenario 46: an edit that keeps the Pair/Draw EMIs publishes no rule version', function () {
    $versionsBefore = RuleVersion::count();

    $this->actingAs(mpSuperAdmin())->withSession(mpConfirmed())
        ->patch(route('super-admin.membership-plans.update', MembershipPlan::where('code', 'B')->firstOrFail()), mpEmiEdit())
        ->assertSessionHasNoErrors();

    expect(RuleVersion::count())->toBe($versionsBefore);
});

test('the history logs only what changed: an unchanged EMI count sent as text is not a change', function () {
    $this->actingAs(mpSuperAdmin())->withSession(mpConfirmed())
        ->patch(route('super-admin.membership-plans.update', MembershipPlan::where('code', 'B')->firstOrFail()),
            mpEmiEdit(['amount' => '2000', 'installment_count' => '10', 'fixed_weight_grams' => '100', 'pair_emis' => '2', 'draw_emis' => '2']))
        ->assertSessionHasNoErrors();

    $change = MembershipPlanChange::sole();
    expect($change->after['installment_count'])->toBe(10)
        ->and($change->before['installment_count'])->toBe(10)
        ->and($change->after['fixed_weight_grams'])->toEqual($change->before['fixed_weight_grams'])
        ->and($change->after['amount'])->toEqual(2000);
});

test('scenario 46: a plan switched Off is not offered and cannot be joined, but its members keep working', function () {
    $plan = MembershipPlan::where('code', 'B')->firstOrFail();
    $member = mpJoinedMember('MP-OFF', 'B', 1);

    $this->actingAs(mpSuperAdmin())->withSession(mpConfirmed())
        ->patch(route('super-admin.membership-plans.update', $plan), mpEmiEdit(['amount' => 3000, 'installment_count' => 10, 'fixed_weight_grams' => 100, 'is_active' => false]))
        ->assertSessionHasNoErrors();

    $this->get(route('registration.show'))
        ->assertInertia(fn ($page) => $page->where('plans', fn ($plans) => collect($plans)->doesntContain('id', $plan->id)));

    $this->post(route('registration.store'), [
        'sponsor_code' => Member::where('is_company_root', true)->value('customer_id'),
        'placement_side' => 'left',
        'name' => 'Too Late',
        'gender' => 'female',
        'email' => 'too-late@example.test',
        'mobile' => '9876500002',
        'membership_plan_id' => $plan->id,
        'payment_mode' => 'cash',
    ])->assertSessionHasErrors('membership_plan_id');

    expect($member->emiSchedule()->firstOrFail()->installments()->where('status', '!=', 'paid')->count())->toBe(9);
});

test('scenario 46: the plan used by Daily Dummy Entries cannot be switched off', function () {
    $plan = MembershipPlan::where('code', 'A')->firstOrFail();

    $this->actingAs(mpSuperAdmin())->withSession(mpConfirmed())
        ->patch(route('super-admin.membership-plans.update', $plan), mpEmiEdit([
            'name' => 'Silver Start', 'amount' => 1000, 'installment_count' => 20, 'fixed_weight_grams' => 100,
            'pair_emis' => 6, 'draw_emis' => 6, 'is_active' => false,
        ]))
        ->assertSessionHasErrors('is_active');

    expect($plan->fresh()->is_active)->toBeTrue()
        ->and(MembershipPlanChange::count())->toBe(0);
});

test('scenario 47: adding an EMI plan gives it code I and publishes its Pair/Draw EMIs, carrying every other value forward', function () {
    $rules = app(RuleVersionService::class);
    $pairBefore = (array) $rules->value('pair_qualification_emis');
    $levelBefore = $rules->value('level_income_min_directs');

    $this->actingAs(mpSuperAdmin())->withSession(mpConfirmed())
        ->post(route('super-admin.membership-plans.store'), [
            'name' => 'Silver Mini',
            'product_category' => 'silver',
            'plan_type' => 'emi',
            'amount' => 500,
            'installment_count' => 24,
            'fixed_weight_grams' => 50,
            'pair_emis' => 4,
            'draw_emis' => 4,
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    $plan = MembershipPlan::where('name', 'Silver Mini')->firstOrFail();
    expect($plan->code)->toBe('I')
        ->and($plan->product_category)->toBe('silver')
        ->and($plan->installment_count)->toBe(24)
        ->and((float) $plan->fixed_weight_grams)->toBe(50.0);

    $pair = (array) $rules->value('pair_qualification_emis');
    expect($pair['I'])->toBe(4)
        ->and(((array) $rules->value('draw_eligibility_emis'))['I'])->toBe(4)
        ->and(collect($pair)->except('I')->all())->toBe($pairBefore)
        ->and($rules->value('level_income_min_directs'))->toBe($levelBefore);

    $this->get(route('registration.show'))
        ->assertInertia(fn ($page) => $page->where('plans', fn ($plans) => collect($plans)->contains('id', $plan->id)));

    expect(MembershipPlanChange::where('action', 'created')->sole()->after['pair_emis'])->toBe(4);
});

test('scenario 47: adding a Direct plan gives the next code and publishes no rule version', function () {
    MembershipPlan::create(['code' => 'I', 'name' => 'Existing I', 'amount' => 1, 'installment_count' => null, 'product_category' => 'silver']);
    $versionsBefore = RuleVersion::count();

    $this->actingAs(mpSuperAdmin())->withSession(mpConfirmed())
        ->post(route('super-admin.membership-plans.store'), [
            'name' => 'Gold Max Direct',
            'product_category' => 'gold',
            'plan_type' => 'direct',
            'amount' => 200000,
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    $plan = MembershipPlan::where('name', 'Gold Max Direct')->firstOrFail();
    expect($plan->code)->toBe('J')
        ->and($plan->installment_count)->toBeNull()
        ->and($plan->fixed_weight_grams)->toBeNull()
        ->and(RuleVersion::count())->toBe($versionsBefore);
});

test('scenario 47: incomplete or forbidden input is refused without a change', function () {
    $admin = mpSuperAdmin();
    $plansBefore = MembershipPlan::count();

    // An EMI plan without its EMI count, weight and Pair/Draw EMIs, and a zero amount.
    $this->actingAs($admin)->withSession(mpConfirmed())
        ->post(route('super-admin.membership-plans.store'), [
            'name' => 'Broken', 'product_category' => 'silver', 'plan_type' => 'emi', 'amount' => 0, 'is_active' => true,
        ])
        ->assertSessionHasErrors(['amount', 'installment_count', 'fixed_weight_grams', 'pair_emis', 'draw_emis']);

    // Metal and type are fixed on an edit.
    $plan = MembershipPlan::where('code', 'B')->firstOrFail();
    $this->actingAs($admin)->withSession(mpConfirmed())
        ->patch(route('super-admin.membership-plans.update', $plan), mpEmiEdit(['product_category' => 'gold', 'plan_type' => 'direct']))
        ->assertSessionHasErrors(['product_category', 'plan_type']);

    // Pair EMIs cannot exceed the EMI count.
    $this->actingAs($admin)->withSession(mpConfirmed())
        ->patch(route('super-admin.membership-plans.update', $plan), mpEmiEdit(['pair_emis' => 13]))
        ->assertSessionHasErrors('pair_emis');

    expect(MembershipPlan::count())->toBe($plansBefore)
        ->and((float) $plan->fresh()->amount)->toBe(3000.0)
        ->and(MembershipPlanChange::count())->toBe(0);
});

test('only a Super Admin who re-entered the password can open or change plans', function () {
    $plan = MembershipPlan::where('code', 'B')->firstOrFail();
    $companyAdmin = User::factory()->create(['role' => 'admin']);

    // First, before any session is set (withSession carries over to later requests in the same test).
    $this->actingAs(mpSuperAdmin())
        ->get(route('super-admin.membership-plans.index'))->assertRedirect(route('password.confirm'));

    $this->actingAs($companyAdmin)->withSession(mpConfirmed())
        ->get(route('super-admin.membership-plans.index'))->assertForbidden();
    $this->actingAs($companyAdmin)->withSession(mpConfirmed())
        ->patch(route('super-admin.membership-plans.update', $plan), mpEmiEdit())->assertForbidden();

    // Member count (browser check 18-10-2026): an assigned dummy is a real leader and counts; an unassigned one does not.
    $assigned = mpJoinedMember('MP-ASSIGNED', 'A', 1);
    $assigned->update(['is_company_dummy' => true, 'dummy_status' => 'assigned']);
    mpJoinedMember('MP-UNASSIGNED', 'A', 1)->update(['is_company_dummy' => true, 'dummy_status' => 'unassigned']);
    mpJoinedMember('MP-REAL', 'A', 1);

    $this->actingAs(mpSuperAdmin())->withSession(mpConfirmed())
        ->get(route('super-admin.membership-plans.index'))
        ->assertInertia(fn ($page) => $page->component('super-admin/membership-plans')
            ->where('plans.0.members_count', 2)
            ->has('plans', 8)
            ->where('plans.1.code', 'B')
            ->where('plans.1.pair_emis', 2)
            ->where('plans.0.is_dummy_plan', true));

    expect((float) $plan->fresh()->amount)->toBe(3000.0);
});

test('the migration back-fill rule: every new membership schedule keeps the plan figures it joined at', function () {
    $plan = MembershipPlan::where('code', 'A')->firstOrFail();

    $member = mpRegister('mp-a@example.test', '9876500003', $plan->id);

    $schedule = $member->emiSchedule()->firstOrFail();
    expect((float) $schedule->plan_installment_amount)->toBe(1000.0)
        ->and((float) $schedule->plan_fixed_weight_grams)->toBe(100.0);
});

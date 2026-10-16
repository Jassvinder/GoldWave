<?php

use App\Actions\Draw\ExecuteMonthlyDraw;
use App\Actions\Draw\GenerateDrawGroups;
use App\Actions\Members\ApplyMemberRestriction;
use App\Actions\Members\LiftMemberRestriction;
use App\Actions\Payments\ApproveCashPayment;
use App\Actions\Payout\SubmitPayoutRequest;
use App\Jobs\ProcessBoosterPayouts;
use App\Models\BoosterPayoutSchedule;
use App\Models\BoosterQualification;
use App\Models\EmiInstallment;
use App\Models\EmiSchedule;
use App\Models\IncomeLedgerCalculation;
use App\Models\Member;
use App\Models\MemberBankDetail;
use App\Models\MemberRestriction;
use App\Models\MembershipPlan;
use App\Models\PairEntry;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\RuleValue;
use App\Models\RuleVersion;
use App\Models\User;
use App\Services\WalletLedgerService;
use Illuminate\Validation\ValidationException;

/**
 * T-231 (DOMAIN_LOGIC.md §2.3, Docs/TEST.md scenarios 44–45) — Payout Hold and Account Block.
 */
function mrMember(string $customerId, ?Member $sponsor = null, ?Member $placementParent = null, string $side = 'left', string $planCode = 'E'): Member
{
    return Member::create([
        'user_id' => User::factory()->create(['role' => 'member'])->id,
        'customer_id' => $customerId,
        'sponsor_id' => $sponsor?->id,
        'placement_parent_id' => $placementParent?->id,
        'placement_side' => $placementParent ? $side : null,
        'membership_plan_id' => MembershipPlan::where('code', $planCode)->value('id'),
        'status' => 'active',
        'activated_at' => now(),
    ]);
}

function mrPendingPayout(Member $member, float $wallet, float $amount): PayoutRequest
{
    app(WalletLedgerService::class)->credit($member, 'level_income', $wallet, null, 'seed credit');
    $bank = MemberBankDetail::create([
        'member_id' => $member->id, 'account_holder_name' => 'Holder', 'account_number' => '1234567890',
        'ifsc_code' => 'TEST0001234', 'bank_name' => 'Test Bank', 'verified_at' => now(),
    ]);

    return app(SubmitPayoutRequest::class)($member->fresh(), $amount, $bank);
}

function mrSuperAdmin(): User
{
    return User::factory()->create(['role' => 'super_admin', 'name' => 'Lakhdataar']);
}

/** Registers a cash member under $sponsor (placed on $side) and approves the payment, as on the live site. */
function mrJoinUnder(Member $sponsor, string $email, string $side = 'left'): Member
{
    test()->post('/join', [
        'sponsor_code' => $sponsor->customer_id,
        'placement_side' => $side,
        'gender' => 'male',
        'name' => 'Joiner '.$email,
        'email' => $email,
        'mobile' => '98'.random_int(10000000, 99999999),
        'membership_plan_id' => MembershipPlan::where('code', 'E')->value('id'),
        'payment_mode' => 'cash',
    ])->assertRedirect();

    $member = User::where('email', $email)->firstOrFail()->member;
    app(ApproveCashPayment::class)($member->payments()->where('type', 'registration')->firstOrFail(), mrSuperAdmin());

    return $member->fresh();
}

beforeEach(function () {
    $this->seed();
});

test('only Super Admin can restrict, and only with a reason, the typed Customer ID and the right password', function () {
    $member = mrMember('MR-VALID');
    $admin = mrSuperAdmin();
    $payload = ['member_id' => $member->id, 'type' => 'payout_hold', 'reason' => 'KYC under review', 'confirm_customer_id' => 'mr-valid', 'password' => 'password'];

    $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/super-admin/member-restrictions/apply', $payload)->assertForbidden();
    $this->actingAs($admin)->post('/super-admin/member-restrictions/apply', [...$payload, 'reason' => 'short'])->assertSessionHasErrors('reason');
    $this->actingAs($admin)->post('/super-admin/member-restrictions/apply', [...$payload, 'confirm_customer_id' => 'MR-OTHER'])->assertSessionHasErrors('confirm_customer_id');
    $this->actingAs($admin)->post('/super-admin/member-restrictions/apply', [...$payload, 'password' => 'wrong'])->assertSessionHasErrors('password');
    expect($member->fresh()->payout_hold_at)->toBeNull();

    // The company root is never restricted.
    $root = Member::where('customer_id', 'GWL-ROOT')->sole();
    expect(fn () => app(ApplyMemberRestriction::class)($root, MemberRestriction::ACCOUNT_BLOCK, 'Testing the root block', $admin))
        ->toThrow(ValidationException::class);

    // Lower-case Customer ID typed, right password: applied.
    $this->actingAs($admin)->post('/super-admin/member-restrictions/apply', $payload)->assertSessionHasNoErrors();
    expect($member->fresh()->payout_hold_at)->not->toBeNull();
});

test('scenario 44: a Payout Hold cancels pending requests and stops withdrawals, but login and earnings carry on', function () {
    $member = mrMember('MR-HOLD');
    $request = mrPendingPayout($member, 5000, 2000);
    expect((float) app(WalletLedgerService::class)->availableBalance($member->fresh()))->toBe(3000.0);
    $admin = mrSuperAdmin();

    $this->actingAs($admin)->post('/super-admin/member-restrictions/apply', [
        'member_id' => $member->id, 'type' => 'payout_hold', 'reason' => 'KYC under review',
        'confirm_customer_id' => 'MR-HOLD', 'password' => 'password',
    ])->assertSessionHasNoErrors();

    expect($request->fresh()->status)->toBe('cancelled')
        ->and((float) app(WalletLedgerService::class)->availableBalance($member->fresh()))->toBe(5000.0);

    // Login works; a new payout request and a wallet-paid registration are refused.
    $this->post('/member/login/password', ['customer_id' => 'MR-HOLD', 'password' => 'password'])->assertSessionHasNoErrors();
    $bank = MemberBankDetail::where('member_id', $member->id)->sole();
    expect(fn () => app(SubmitPayoutRequest::class)($member->fresh(), 1000, $bank))->toThrow(ValidationException::class);
    $this->actingAs($member->user)->postJson('/member/register-new/wallet-otp', [])->assertStatus(422);
    $this->actingAs($member->user)->get('/member/payout')->assertInertia(fn ($page) => $page->where('on_hold_message', SubmitPayoutRequest::ON_HOLD_MESSAGE));

    // Earnings carry on: a joining under the held member is not skipped for them.
    $joiner = mrJoinUnder($member->fresh(), 'hold-joiner@example.test');
    expect(IncomeLedgerCalculation::where('source_payment_id', $joiner->payments()->value('id'))->where('beneficiary_member_id', $member->id)->value('skip_reason'))
        ->not->toBe('member_blocked');

    // Lifted: requests are accepted again; the history shows who and why.
    app(LiftMemberRestriction::class)($member, MemberRestriction::PAYOUT_HOLD, 'Documents verified OK', $admin);
    expect(app(SubmitPayoutRequest::class)($member->fresh(), 1000, $bank)->status)->toBe('pending');
    $row = MemberRestriction::where('member_id', $member->id)->sole();
    expect($row->reason)->toBe('KYC under review')->and($row->end_reason)->toBe('Documents verified OK')->and($row->ended_by)->toBe($admin->id);
});

test('scenario 45: a block ends the session, refuses login and cancels pending payouts; the wallet stays', function () {
    $member = mrMember('MR-BLOCK');
    $request = mrPendingPayout($member, 8000, 3000);
    $admin = mrSuperAdmin();

    $this->actingAs($member->user)->get('/dashboard')->assertOk();
    app(ApplyMemberRestriction::class)($member, MemberRestriction::ACCOUNT_BLOCK, 'Fraud complaint received', $admin);

    // The open session is signed out on its next request.
    $this->get('/dashboard')->assertRedirect(route('member.login'));
    $this->assertGuest();

    $this->post('/member/login/password', ['customer_id' => 'MR-BLOCK', 'password' => 'password'])
        ->assertSessionHasErrors(['customer_id' => Member::BLOCKED_LOGIN_MESSAGE]);
    $this->post('/member/login/otp/request', ['identifier' => $member->user->email])
        ->assertSessionHasErrors(['identifier' => Member::BLOCKED_LOGIN_MESSAGE]);

    expect($request->fresh()->status)->toBe('cancelled')
        ->and((float) $member->fresh()->wallet_balance)->toBe(8000.0);
});

test('scenario 45: while blocked the member earns nothing from a joining, but the uplines are not affected', function () {
    $upline = mrMember('MR-UP');
    $blocked = mrMember('MR-B', $upline, $upline);
    app(ApplyMemberRestriction::class)($blocked, MemberRestriction::ACCOUNT_BLOCK, 'Fraud complaint received', mrSuperAdmin());

    $joiner = mrJoinUnder($blocked->fresh(), 'block-joiner@example.test');
    $paymentId = $joiner->payments()->where('type', 'registration')->value('id');

    // Level Income: level 1 (the blocked sponsor) skipped for good; level 2 (the upline) not.
    expect(IncomeLedgerCalculation::where('source_payment_id', $paymentId)->where('level_no', 1)->value('skip_reason'))->toBe('member_blocked')
        ->and(IncomeLedgerCalculation::where('source_payment_id', $paymentId)->where('level_no', 2)->value('skip_reason'))->not->toBe('member_blocked');

    // Pair: no entry for the blocked member, the upline gets theirs.
    expect(PairEntry::where('member_id', $blocked->id)->count())->toBe(0)
        ->and(PairEntry::where('member_id', $upline->id)->count())->toBe(1);

    // Unblocked: the next joining counts again.
    app(LiftMemberRestriction::class)($blocked, MemberRestriction::ACCOUNT_BLOCK, 'Complaint withdrawn', mrSuperAdmin());
    mrJoinUnder($blocked->fresh(), 'after-unblock@example.test');
    expect(PairEntry::where('member_id', $blocked->id)->count())->toBe(1);
});

test('scenario 45: a Booster month due inside the block is skipped for good; one due before it waits; later months pay', function () {
    $member = mrMember('MR-BOOST');
    $qualification = BoosterQualification::create([
        'member_id' => $member->id, 'level_no' => 1, 'qualified_at' => now()->subMonths(3),
        'rule_version_id' => RuleVersion::where('is_active', true)->value('id'),
    ]);
    $beforeBlock = $qualification->payoutSchedules()->create(['month_no' => 3, 'scheduled_date' => now()->subDays(5)->toDateString(), 'amount' => 5000, 'status' => 'pending']);
    $duringBlock = $qualification->payoutSchedules()->create(['month_no' => 4, 'scheduled_date' => now()->toDateString(), 'amount' => 5000, 'status' => 'pending']);
    $afterUnblock = $qualification->payoutSchedules()->create(['month_no' => 5, 'scheduled_date' => now()->addMonth()->toDateString(), 'amount' => 5000, 'status' => 'pending']);

    app(ApplyMemberRestriction::class)($member, MemberRestriction::ACCOUNT_BLOCK, 'Fraud complaint received', mrSuperAdmin());
    app(ProcessBoosterPayouts::class)->handle(app(WalletLedgerService::class));

    expect($duringBlock->fresh()->status)->toBe('skipped')
        ->and($beforeBlock->fresh()->status)->toBe('pending')
        ->and((float) $member->fresh()->wallet_balance)->toBe(0.0);

    app(LiftMemberRestriction::class)($member, MemberRestriction::ACCOUNT_BLOCK, 'Complaint withdrawn', mrSuperAdmin());
    $this->travel(1)->months();
    app(ProcessBoosterPayouts::class)->handle(app(WalletLedgerService::class));

    expect($beforeBlock->fresh()->status)->toBe('paid')
        ->and($afterUnblock->fresh()->status)->toBe('paid')
        ->and($duringBlock->fresh()->status)->toBe('skipped')
        ->and(BoosterPayoutSchedule::where('status', 'paid')->sum('amount'))->toEqual(10000);
});

test('scenario 45: a blocked member is not grouped or drawn, and the group never completes on them', function () {
    RuleValue::where('key', 'draw_group_size')->update(['value' => 3]);
    $a = mrMember('MR-DA');
    $b = mrMember('MR-DB');
    $c = mrMember('MR-DC');
    $group = app(GenerateDrawGroups::class)()[0];
    app(ApplyMemberRestriction::class)($b, MemberRestriction::ACCOUNT_BLOCK, 'Fraud complaint received', mrSuperAdmin());

    // Manual entry refuses the blocked member.
    expect(fn () => app(ExecuteMonthlyDraw::class)->recordManual($group, ['MR-DB'], mrSuperAdmin()))->toThrow(ValidationException::class);

    // Two draws: only A and C can win; with B left the group is still active.
    app(ExecuteMonthlyDraw::class)(oncePerCalendarMonth: false);
    app(ExecuteMonthlyDraw::class)(oncePerCalendarMonth: false);
    $winners = $group->executions()->pluck('winner_member_id')->all();
    expect($winners)->toEqualCanonicalizing([$a->id, $c->id])
        ->and($group->fresh()->status)->toBe('active');

    // A blocked member who is not grouped yet joins no new group.
    $d = mrMember('MR-DD');
    app(ApplyMemberRestriction::class)($d, MemberRestriction::ACCOUNT_BLOCK, 'Fraud complaint received', mrSuperAdmin());
    expect(app(GenerateDrawGroups::class)->eligibleUngroupedMemberIds())->not->toContain($d->id);
});

test('scenario 45: Super Admin records a blocked member\'s cash EMI on Member Detail; unblock can also lift the hold', function () {
    $member = mrMember('MR-EMI', planCode: 'A');
    $plan = MembershipPlan::where('code', 'A')->firstOrFail();
    $schedule = EmiSchedule::create([
        'member_id' => $member->id, 'membership_plan_id' => $plan->id, 'total_installments' => $plan->installment_count,
        'rate_booking_method' => 'future_rate', 'installment_amount' => $plan->amount,
    ]);
    $installment = EmiInstallment::create(['emi_schedule_id' => $schedule->id, 'installment_no' => 2, 'due_date' => now()->toDateString(), 'amount' => $plan->amount, 'status' => 'due']);
    $admin = mrSuperAdmin();

    // Not offered while the member is not blocked.
    $this->actingAs($admin)->get("/super-admin/members/{$member->id}")->assertInertia(fn ($page) => $page->where('record_cash_emi', null));
    $this->actingAs($admin)->post("/super-admin/members/{$member->id}/record-cash-emi")->assertSessionHasErrors('emi');

    app(ApplyMemberRestriction::class)($member, MemberRestriction::PAYOUT_HOLD, 'First warning given', $admin);
    app(ApplyMemberRestriction::class)($member, MemberRestriction::ACCOUNT_BLOCK, 'Second offence found', $admin);

    $this->actingAs($admin)->get("/super-admin/members/{$member->id}")
        ->assertInertia(fn ($page) => $page->where('record_cash_emi.installment_no', 2)->has('restrictions', 2));
    $this->actingAs($admin)->post("/super-admin/members/{$member->id}/record-cash-emi")->assertSessionHasNoErrors();

    expect($installment->fresh()->status)->toBe('paid')
        ->and(Payment::find($installment->fresh()->payment_id)->cash_status)->toBe('approved');

    // Unblock with "Also lift the Payout Hold" ticked: both end.
    $this->actingAs($admin)->post('/super-admin/member-restrictions/lift', [
        'member_id' => $member->id, 'type' => 'account_block', 'reason' => 'Case fully cleared',
        'confirm_customer_id' => 'MR-EMI', 'password' => 'password', 'also_lift_payout_hold' => true,
    ])->assertSessionHasNoErrors();

    expect($member->fresh()->blocked_at)->toBeNull()
        ->and($member->fresh()->payout_hold_at)->toBeNull()
        ->and(MemberRestriction::where('member_id', $member->id)->whereNull('ended_at')->count())->toBe(0);
});

test('unblocking without "Also lift the Payout Hold" keeps the hold', function () {
    $member = mrMember('MR-KEEP');
    $admin = mrSuperAdmin();
    app(ApplyMemberRestriction::class)($member, MemberRestriction::PAYOUT_HOLD, 'First warning given', $admin);
    app(ApplyMemberRestriction::class)($member, MemberRestriction::ACCOUNT_BLOCK, 'Second offence found', $admin);

    app(LiftMemberRestriction::class)($member, MemberRestriction::ACCOUNT_BLOCK, 'Compensation agreed', $admin, alsoLiftPayoutHold: false);

    expect($member->fresh()->blocked_at)->toBeNull()
        ->and($member->fresh()->payout_hold_at)->not->toBeNull();

    $this->actingAs($admin)->get('/super-admin/member-restrictions')
        ->assertInertia(fn ($page) => $page->has('restricted', 1)->where('restricted.0.customer_id', 'MR-KEEP')->has('history', 2));
    $this->actingAs($admin)->get('/super-admin/members?restriction=payout_hold')
        ->assertInertia(fn ($page) => $page->has('members.data', 1)->where('members.data.0.customer_id', 'MR-KEEP'));
});

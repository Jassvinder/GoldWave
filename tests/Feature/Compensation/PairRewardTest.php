<?php

use App\Actions\Compensation\CreatePairEntries;
use App\Actions\Compensation\EvaluatePairMilestones;
use App\Models\EmiInstallment;
use App\Models\EmiSchedule;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\PairEntry;
use App\Models\PairRewardTransaction;
use App\Models\Payment;
use App\Models\RuleValue;
use App\Models\User;
use App\Services\PairQualifiedDirects;
use Illuminate\Support\Carbon;

/**
 * DOMAIN_LOGIC.md §7 (Reward/Pair Income), Docs/TEST.md scenario 2
 * (incremental consumption + carry-forward, exact worked numbers) and
 * scenario 8 (EMI Pair-Qualification threshold) plus the new
 * Beneficiary-Chain-Rule regression (team-size fan-out, resolved 13-09-2026).
 */
function pairMember(string $customerId, ?Member $placementParent = null, string $side = 'left', ?MembershipPlan $plan = null, string $status = 'active'): Member
{
    $user = User::factory()->create(['role' => 'member']);

    return Member::create([
        'user_id' => $user->id,
        'customer_id' => $customerId,
        'placement_parent_id' => $placementParent?->id,
        'placement_side' => $placementParent ? $side : null,
        'membership_plan_id' => $plan?->id,
        'status' => $status,
        'activated_at' => $status === 'active' ? now() : null,
    ]);
}

/** A qualified direct (T-178): active, one-time Plan E, registration paid. */
function addActiveDirect(Member $sponsor, string $customerId, string $status = 'active'): Member
{
    $user = User::factory()->create(['role' => 'member']);

    $direct = Member::create([
        'user_id' => $user->id,
        'customer_id' => $customerId,
        'sponsor_id' => $sponsor->id,
        'membership_plan_id' => MembershipPlan::where('code', 'E')->value('id'),
        'status' => $status,
        'activated_at' => now(),
    ]);

    Payment::create([
        'member_id' => $direct->id,
        'type' => 'registration',
        'amount' => 20000,
        'mode' => 'cash',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    return $direct;
}

/** An active Plan A (EMI) direct with `$paidEmis` installments paid — Plan A needs 6 for Pair qualification. */
function addEmiDirect(Member $sponsor, string $customerId, int $paidEmis): Member
{
    $plan = MembershipPlan::where('code', 'A')->firstOrFail();
    $direct = Member::create([
        'user_id' => User::factory()->create(['role' => 'member'])->id,
        'customer_id' => $customerId,
        'sponsor_id' => $sponsor->id,
        'membership_plan_id' => $plan->id,
        'status' => 'active',
        'activated_at' => now(),
    ]);
    $schedule = makeEmiSchedule($direct, $plan);

    for ($i = 1; $i <= $paidEmis; $i++) {
        payEmiInstallment($direct, $schedule, $i);
    }

    return $direct;
}

function seedPairEntries(Member $beneficiary, string $side, int $count, string $metal = 'silver'): void
{
    for ($i = 0; $i < $count; $i++) {
        $payment = Payment::create([
            'member_id' => $beneficiary->id,
            'type' => 'registration',
            'amount' => 1,
            'mode' => 'cash',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        PairEntry::create([
            'member_id' => $beneficiary->id,
            'side' => $side,
            'metal' => $metal,
            'source_payment_id' => $payment->id,
            'status' => 'unused',
        ]);
    }
}

function makeEmiSchedule(Member $member, MembershipPlan $plan): EmiSchedule
{
    return EmiSchedule::create([
        'member_id' => $member->id,
        'membership_plan_id' => $plan->id,
        'total_installments' => $plan->installment_count,
        'rate_booking_method' => 'future_rate',
        'installment_amount' => $plan->amount,
    ]);
}

function payEmiInstallment(Member $member, EmiSchedule $schedule, int $installmentNo): Payment
{
    $payment = Payment::create([
        'member_id' => $member->id,
        'type' => 'emi_installment',
        'amount' => $schedule->membershipPlan->amount,
        'mode' => 'cash',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    EmiInstallment::create([
        'emi_schedule_id' => $schedule->id,
        'installment_no' => $installmentNo,
        'due_date' => now(),
        'amount' => $schedule->membershipPlan->amount,
        'status' => 'paid',
        'payment_id' => $payment->id,
    ]);

    return $payment;
}

beforeEach(function () {
    $this->seed();
});

test('Plan A (6 completed EMIs required) creates zero pair_entries for installments 1-5 and exactly one at installment 6', function () {
    $root = pairMember('PA-ROOT');
    $planA = MembershipPlan::where('code', 'A')->firstOrFail();
    $member = pairMember('PA-MEMBER', $root, 'left', $planA);
    $schedule = makeEmiSchedule($member, $planA);

    for ($i = 1; $i <= 5; $i++) {
        $payment = payEmiInstallment($member, $schedule, $i);
        app(CreatePairEntries::class)($payment);
        expect(PairEntry::count())->toBe(0);
    }

    $sixthPayment = payEmiInstallment($member, $schedule, 6);
    app(CreatePairEntries::class)($sixthPayment);

    expect(PairEntry::count())->toBe(1);
    $entry = PairEntry::firstOrFail();
    expect($entry->member_id)->toBe($root->id);
    expect($entry->side)->toBe('left');
    expect($entry->source_payment_id)->toBe($sixthPayment->id);

    // Regression: installments 7+ never create additional pair entries for the same joining.
    $seventhPayment = payEmiInstallment($member, $schedule, 7);
    app(CreatePairEntries::class)($seventhPayment);
    expect(PairEntry::count())->toBe(1);
});

test('Plan D (1 completed EMI required) creates exactly one pair_entries row on the very first installment', function () {
    $root = pairMember('PD-ROOT');
    $planD = MembershipPlan::where('code', 'D')->firstOrFail();
    $member = pairMember('PD-MEMBER', $root, 'right', $planD);
    $schedule = makeEmiSchedule($member, $planD);

    $firstPayment = payEmiInstallment($member, $schedule, 1);
    app(CreatePairEntries::class)($firstPayment);

    expect(PairEntry::count())->toBe(1);
    expect(PairEntry::firstOrFail()->member_id)->toBe($root->id);
    expect(PairEntry::firstOrFail()->side)->toBe('right');
});

test('a one-time-plan registration fans a pair entry out to every Binary Position ancestor (team size, not direct-to-direct)', function () {
    // P3 <- (right) - P2 <- (left) - P1 <- (right) - N, per Docs/TEST.md's Beneficiary-Chain regression.
    $p3 = pairMember('CHAIN-P3');
    $p2 = pairMember('CHAIN-P2', $p3, 'right');
    $p1 = pairMember('CHAIN-P1', $p2, 'left');
    $planF = MembershipPlan::where('code', 'F')->firstOrFail();
    $n = pairMember('CHAIN-N', $p1, 'right', $planF);

    $payment = Payment::create([
        'member_id' => $n->id,
        'type' => 'registration',
        'amount' => 50000,
        'mode' => 'cash',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    app(CreatePairEntries::class)($payment);

    expect(PairEntry::count())->toBe(3);

    $forP1 = PairEntry::where('member_id', $p1->id)->firstOrFail();
    $forP2 = PairEntry::where('member_id', $p2->id)->firstOrFail();
    $forP3 = PairEntry::where('member_id', $p3->id)->firstOrFail();

    expect($forP1->side)->toBe('right');
    expect($forP2->side)->toBe('left');
    expect($forP3->side)->toBe('right');
    expect([$forP1->source_payment_id, $forP2->source_payment_id, $forP3->source_payment_id])->each->toBe($payment->id);
});

test('an unassigned dummy ancestor is skipped as a beneficiary but still lets a real ancestor further up receive theirs', function () {
    // DOMAIN_LOGIC.md §14.2 point 5 / §21 T-013 pre-coding pass: a dummy never
    // becomes a compensation beneficiary itself, even while sitting inside a
    // real registrant's Binary Position ancestor chain.
    $p3 = pairMember('CHAIN-DUMMY-P3');
    $dummyP2 = Member::create([
        'customer_id' => 'CHAIN-DUMMY-P2',
        'placement_parent_id' => $p3->id,
        'placement_side' => 'right',
        'status' => 'active',
        'is_company_dummy' => true,
        'dummy_status' => 'unassigned',
    ]);
    $planF = MembershipPlan::where('code', 'F')->firstOrFail();
    $n = pairMember('CHAIN-DUMMY-N', $dummyP2, 'left', $planF);

    $payment = Payment::create([
        'member_id' => $n->id,
        'type' => 'registration',
        'amount' => 50000,
        'mode' => 'cash',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    app(CreatePairEntries::class)($payment);

    expect(PairEntry::count())->toBe(1);
    expect(PairEntry::where('member_id', $dummyP2->id)->exists())->toBeFalse();
    expect(PairEntry::where('member_id', $p3->id)->exists())->toBeTrue();
});

test('calling CreatePairEntries twice for the same qualifying payment never duplicates rows', function () {
    $root = pairMember('IDEMP-ROOT');
    $planF = MembershipPlan::where('code', 'F')->firstOrFail();
    $member = pairMember('IDEMP-MEMBER', $root, 'left', $planF);

    $payment = Payment::create([
        'member_id' => $member->id,
        'type' => 'registration',
        'amount' => 50000,
        'mode' => 'cash',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    app(CreatePairEntries::class)($payment);
    app(CreatePairEntries::class)($payment);

    expect(PairEntry::count())->toBe(1);
});

test('TEST.md scenario 2: incremental consumption crosses milestone 1, carries the remainder forward, then crosses milestone 2 next month', function () {
    $beneficiary = pairMember('SC2-BENEFICIARY');
    addActiveDirect($beneficiary, 'SC2-D1');
    addActiveDirect($beneficiary, 'SC2-D2');

    seedPairEntries($beneficiary, 'left', 3);
    seedPairEntries($beneficiary, 'right', 3);
    seedPairEntries($beneficiary, 'left', 50);
    seedPairEntries($beneficiary, 'right', 50);

    app(EvaluatePairMilestones::class)($beneficiary, Carbon::create(2026, 1, 31));

    expect(PairRewardTransaction::where('member_id', $beneficiary->id)->count())->toBe(1);
    $milestone1 = PairRewardTransaction::where('member_id', $beneficiary->id)->where('milestone_no', 1)->firstOrFail();
    expect((float) $milestone1->reward_amount)->toBe(500.0);
    expect($milestone1->left_consumed_count)->toBe(5);
    expect($milestone1->right_consumed_count)->toBe(5);

    expect(PairEntry::where('member_id', $beneficiary->id)->where('side', 'left')->where('status', 'unused')->count())->toBe(48);
    expect(PairEntry::where('member_id', $beneficiary->id)->where('side', 'right')->where('status', 'unused')->count())->toBe(48);
    expect(PairEntry::where('member_id', $beneficiary->id)->where('consumed_for_milestone_no', 1)->count())->toBe(10);
    expect((float) $beneficiary->fresh()->wallet_balance)->toBe(500.0);

    // Next month: 5 more each arrive (unused pool becomes 53L/53R again), and 2 more directs (M2 needs 4 in total, T-178).
    seedPairEntries($beneficiary, 'left', 5);
    seedPairEntries($beneficiary, 'right', 5);
    addActiveDirect($beneficiary, 'SC2-D3');
    addActiveDirect($beneficiary, 'SC2-D4');

    app(EvaluatePairMilestones::class)($beneficiary, Carbon::create(2026, 2, 28));

    expect(PairRewardTransaction::where('member_id', $beneficiary->id)->count())->toBe(2);
    $milestone2 = PairRewardTransaction::where('member_id', $beneficiary->id)->where('milestone_no', 2)->firstOrFail();
    expect((float) $milestone2->reward_amount)->toBe(5000.0);

    expect(PairEntry::where('member_id', $beneficiary->id)->where('side', 'left')->where('status', 'unused')->count())->toBe(3);
    expect(PairEntry::where('member_id', $beneficiary->id)->where('side', 'right')->where('status', 'unused')->count())->toBe(3);
    expect((float) $beneficiary->fresh()->wallet_balance)->toBe(5500.0);

    // Invariant: the 5L/5R consumed for milestone 1 are never reused by milestone 2's evaluation.
    expect(PairEntry::where('consumed_for_milestone_no', 1)->count())->toBe(10);
    expect(PairEntry::where('consumed_for_milestone_no', 2)->count())->toBe(100);
});

test('a milestone whose Left/Right counts are met but whose Min. Direct Members gate is not is left unconsumed until the gate is met', function () {
    $beneficiary = pairMember('GATE-BENEFICIARY');
    // Only 1 active direct — below the default minimum of 2.
    addActiveDirect($beneficiary, 'GATE-D1');

    seedPairEntries($beneficiary, 'left', 5);
    seedPairEntries($beneficiary, 'right', 5);

    app(EvaluatePairMilestones::class)($beneficiary, Carbon::create(2026, 1, 31));

    expect(PairRewardTransaction::where('member_id', $beneficiary->id)->count())->toBe(0);
    expect(PairEntry::where('member_id', $beneficiary->id)->where('status', 'unused')->count())->toBe(10);
    expect((float) $beneficiary->fresh()->wallet_balance)->toBe(0.0);

    // The gate is now met — the same, still-unconsumed entries pay out on the next run.
    addActiveDirect($beneficiary, 'GATE-D2');
    app(EvaluatePairMilestones::class)($beneficiary, Carbon::create(2026, 2, 28));

    expect(PairRewardTransaction::where('member_id', $beneficiary->id)->count())->toBe(1);
    expect((float) $beneficiary->fresh()->wallet_balance)->toBe(500.0);
});

test('a single monthly run crosses multiple milestones when enough entries exist for both', function () {
    $beneficiary = pairMember('MULTI-BENEFICIARY');
    // M1 needs 2 directs and M2 needs 4 in total (T-178).
    foreach (['MULTI-D1', 'MULTI-D2', 'MULTI-D3', 'MULTI-D4'] as $customerId) {
        addActiveDirect($beneficiary, $customerId);
    }

    // Exactly enough for milestone 1 (5/5) + milestone 2 (50/50) = 55/55.
    seedPairEntries($beneficiary, 'left', 55);
    seedPairEntries($beneficiary, 'right', 55);

    app(EvaluatePairMilestones::class)($beneficiary, Carbon::create(2026, 1, 31));

    expect(PairRewardTransaction::where('member_id', $beneficiary->id)->count())->toBe(2);
    expect((float) $beneficiary->fresh()->wallet_balance)->toBe(500.0 + 5000.0);
    expect(PairEntry::where('member_id', $beneficiary->id)->where('status', 'unused')->count())->toBe(0);
});

test('TEST.md scenario 35: M2 needs 4 qualified directs — with 3 only M1 pays and the entries carry forward, the 4th direct unlocks M2', function () {
    $beneficiary = pairMember('SC35-BENEFICIARY');
    foreach (['SC35-D1', 'SC35-D2', 'SC35-D3'] as $customerId) {
        addActiveDirect($beneficiary, $customerId);
    }
    seedPairEntries($beneficiary, 'left', 55);
    seedPairEntries($beneficiary, 'right', 55);

    app(EvaluatePairMilestones::class)($beneficiary, Carbon::create(2026, 1, 31));

    expect(PairRewardTransaction::where('member_id', $beneficiary->id)->pluck('milestone_no')->all())->toBe([1]);
    expect(PairEntry::where('member_id', $beneficiary->id)->where('side', 'left')->where('status', 'unused')->count())->toBe(50);
    expect(PairEntry::where('member_id', $beneficiary->id)->where('side', 'right')->where('status', 'unused')->count())->toBe(50);
    expect((float) $beneficiary->fresh()->wallet_balance)->toBe(500.0);

    addActiveDirect($beneficiary, 'SC35-D4');
    app(EvaluatePairMilestones::class)($beneficiary, Carbon::create(2026, 2, 28));

    $milestone2 = PairRewardTransaction::where('member_id', $beneficiary->id)->where('milestone_no', 2)->firstOrFail();
    expect((float) $milestone2->reward_amount)->toBe(5000.0);
    expect(PairEntry::where('member_id', $beneficiary->id)->where('status', 'unused')->count())->toBe(0);
    expect((float) $beneficiary->fresh()->wallet_balance)->toBe(5500.0);
});

test('TEST.md scenario 35: an EMI direct counts only once its Pair qualification EMIs are paid, and a non-active (cancelled) direct never counts', function () {
    $beneficiary = pairMember('SC35E-BENEFICIARY');
    addActiveDirect($beneficiary, 'SC35E-D1');
    addActiveDirect($beneficiary, 'SC35E-D2');
    addActiveDirect($beneficiary, 'SC35E-CANCELLED', 'cancelled');
    $emiDirect = addEmiDirect($beneficiary, 'SC35E-EMI', 5); // Plan A needs 6.
    seedPairEntries($beneficiary, 'left', 55);
    seedPairEntries($beneficiary, 'right', 55);

    expect(app(PairQualifiedDirects::class)->count($beneficiary))->toBe(2);

    app(EvaluatePairMilestones::class)($beneficiary, Carbon::create(2026, 1, 31));
    expect(PairRewardTransaction::where('member_id', $beneficiary->id)->pluck('milestone_no')->all())->toBe([1]);

    // 6th EMI paid: 3 qualified directs, still below M2's 4.
    payEmiInstallment($emiDirect, $emiDirect->emiSchedule()->firstOrFail(), 6);
    expect(app(PairQualifiedDirects::class)->count($beneficiary))->toBe(3);

    app(EvaluatePairMilestones::class)($beneficiary, Carbon::create(2026, 2, 28));
    expect(PairRewardTransaction::where('member_id', $beneficiary->id)->count())->toBe(1);
    expect((float) $beneficiary->fresh()->wallet_balance)->toBe(500.0);
});

test('T-110: a milestone consumed from a mix of Gold and Silver entries values each one by its own metal, not one flat rate', function () {
    RuleValue::where('key', 'pair_value_per_entry')->update(['value' => 50]);
    RuleValue::where('key', 'pair_value_per_entry_gold')->update(['value' => 40]);

    $beneficiary = pairMember('MIXED-BENEFICIARY');
    addActiveDirect($beneficiary, 'MIXED-D1');
    addActiveDirect($beneficiary, 'MIXED-D2');

    // Milestone 1 needs 5 Left + 5 Right. Seed 3 Silver + 2 Gold on each side.
    seedPairEntries($beneficiary, 'left', 3, 'silver');
    seedPairEntries($beneficiary, 'left', 2, 'gold');
    seedPairEntries($beneficiary, 'right', 3, 'silver');
    seedPairEntries($beneficiary, 'right', 2, 'gold');

    app(EvaluatePairMilestones::class)($beneficiary, Carbon::create(2026, 1, 31));

    // 6 Silver entries * 50 + 4 Gold entries * 40 = 300 + 160 = 460, never
    // (5 + 5) * one flat value.
    $transaction = PairRewardTransaction::where('member_id', $beneficiary->id)->firstOrFail();
    expect((float) $transaction->reward_amount)->toBe(460.0);
    expect((float) $beneficiary->fresh()->wallet_balance)->toBe(460.0);
});

test('T-110: CreatePairEntries tags every fanned-out entry with the joining member\'s own plan metal', function () {
    $planF = MembershipPlan::where('code', 'F')->firstOrFail(); // Gold one-time.
    $planE = MembershipPlan::where('code', 'E')->firstOrFail(); // Silver one-time.

    $goldSponsor = pairMember('METALTAG-GOLD-SPONSOR');
    $goldJoiner = pairMember('METALTAG-GOLD-JOINER', $goldSponsor, 'left', $planF);
    $goldJoiner->update(['sponsor_id' => $goldSponsor->id]);
    app(CreatePairEntries::class)(Payment::create([
        'member_id' => $goldJoiner->id, 'type' => 'registration', 'amount' => 50000,
        'mode' => 'cash', 'status' => 'paid', 'paid_at' => now(),
    ]));

    $silverSponsor = pairMember('METALTAG-SILVER-SPONSOR');
    $silverJoiner = pairMember('METALTAG-SILVER-JOINER', $silverSponsor, 'left', $planE);
    $silverJoiner->update(['sponsor_id' => $silverSponsor->id]);
    app(CreatePairEntries::class)(Payment::create([
        'member_id' => $silverJoiner->id, 'type' => 'registration', 'amount' => 20000,
        'mode' => 'cash', 'status' => 'paid', 'paid_at' => now(),
    ]));

    expect(PairEntry::where('member_id', $goldSponsor->id)->value('metal'))->toBe('gold');
    expect(PairEntry::where('member_id', $silverSponsor->id)->value('metal'))->toBe('silver');
});

test('registering a one-time plan under a placement chain triggers pair-entry creation automatically through PaymentConfirmed', function () {
    $root = pairMember('E2E-PAIR-ROOT');
    $planF = MembershipPlan::where('code', 'F')->firstOrFail();

    $this->post('/join', [
        'sponsor_code' => $root->customer_id,
        'placement_side' => 'left',
        'gender' => 'male',
        'name' => 'Pair Reward E2E Member',
        'email' => 'pair-reward-e2e@example.test',
        'mobile' => '9876530001',
        'membership_plan_id' => $planF->id,
        'payment_mode' => 'cash',
    ])->assertRedirect();

    $member = Member::whereHas('user', fn ($q) => $q->where('email', 'pair-reward-e2e@example.test'))->firstOrFail();
    $registrationPayment = Payment::where('member_id', $member->id)->where('type', 'registration')->firstOrFail();
    $superAdmin = User::where('role', 'super_admin')->firstOrFail();

    $this->actingAs($superAdmin)
        ->post("/super-admin/cash-payments/{$registrationPayment->id}/approve")
        ->assertRedirect();

    expect(PairEntry::where('member_id', $root->id)->where('source_payment_id', $registrationPayment->id)->exists())->toBeTrue();
});

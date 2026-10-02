<?php

use App\Actions\Compensation\CalculateLevelIncome;
use App\Actions\Compensation\EvaluatePairMilestones;
use App\Actions\Payments\ApproveCashPayment;
use App\Actions\Payments\InitiateEmiInstallmentPayment;
use App\Actions\Payments\InitiateFullEmiPayment;
use App\Jobs\ProcessBoosterPayouts;
use App\Models\BoosterPayoutSchedule;
use App\Models\BoosterQualification;
use App\Models\EmiInstallment;
use App\Models\EmiSchedule;
use App\Models\IncomeLedgerCalculation;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\PairEntry;
use App\Models\PairRewardTransaction;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Services\EarningsVerifier;
use App\Services\WalletLedgerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * T-182 (30-09-2026) — Docs/TEST.md scenario 38: while any of a member's EMIs is overdue, their new earnings are held
 * (a `pending` wallet credit outside the balance) and released the moment the record is clear; Pair/Reward and Booster
 * wait instead.
 */
function oehMember(string $customerId, string $planCode, int $paid = 0, ?Member $sponsor = null): Member
{
    $plan = MembershipPlan::where('code', $planCode)->firstOrFail();
    $member = Member::create([
        'user_id' => User::factory()->create(['role' => 'member'])->id,
        'customer_id' => $customerId,
        'sponsor_id' => $sponsor?->id,
        'membership_plan_id' => $plan->id,
        'status' => 'active',
        'activated_at' => now(),
    ]);

    if (! $plan->isEmiPlan()) {
        Payment::create(['member_id' => $member->id, 'type' => 'registration', 'amount' => $plan->amount, 'mode' => 'cash', 'status' => 'paid', 'paid_at' => now()]);

        return $member->fresh();
    }

    $schedule = EmiSchedule::create([
        'member_id' => $member->id,
        'membership_plan_id' => $plan->id,
        'total_installments' => $plan->installment_count,
        'rate_booking_method' => 'future_rate',
        'installment_amount' => $plan->amount,
        'future_commitment_amount' => (float) $plan->amount * $plan->installment_count,
    ]);

    for ($no = 1; $no <= $plan->installment_count; $no++) {
        EmiInstallment::create([
            'emi_schedule_id' => $schedule->id,
            'installment_no' => $no,
            'due_date' => now()->addMonthsNoOverflow($no - 5)->toDateString(),
            'amount' => $plan->amount,
            'status' => $no <= $paid ? 'paid' : 'upcoming',
        ]);
    }

    return $member->fresh();
}

function oehOverdue(Member $member, array $installmentNos): void
{
    EmiInstallment::where('emi_schedule_id', $member->emiSchedule->id)->whereIn('installment_no', $installmentNos)->update(['status' => 'overdue']);
}

function oehPay(Member $member, int $installmentNo): void
{
    $installment = EmiInstallment::where('emi_schedule_id', $member->emiSchedule->id)->where('installment_no', $installmentNo)->firstOrFail();
    app(ApproveCashPayment::class)(app(InitiateEmiInstallmentPayment::class)($member->fresh(), $installment, 'cash'), User::where('role', 'super_admin')->firstOrFail());
}

/** P joins under M on Plan E and their ₹20,000 registration is calculated: M's Level 1 is ₹1,000. */
function oehDownlineJoins(Member $m, string $customerId): Payment
{
    $p = oehMember($customerId, 'E', 0, $m);
    $payment = Payment::where('member_id', $p->id)->firstOrFail();
    app(CalculateLevelIncome::class)($payment);

    return $payment;
}

beforeEach(function () {
    $this->seed();
    DB::table('rule_values')->where('key', 'level_income_min_directs')->update(['value' => json_encode([])]);
});

test('TEST.md scenario 38: with EMI 5 overdue, M\'s ₹1,000 Level 1 is held outside the balance, and paying EMI 5 releases it', function () {
    $m = oehMember('OEH-M', 'A', 4);
    oehOverdue($m, [5]);

    $payment = oehDownlineJoins($m, 'OEH-P');

    $row = IncomeLedgerCalculation::where('source_payment_id', $payment->id)->where('level_no', 1)->firstOrFail();
    expect($row->eligibility_status)->toBe('paid');
    expect((float) $row->amount)->toBe(1000.0);
    $entry = WalletLedgerEntry::where('source_id', $row->id)->where('category', 'level_income')->firstOrFail();
    expect($entry->status)->toBe('pending');
    expect($entry->statusLabel())->toBe('held (EMI overdue)');
    expect((float) $m->fresh()->wallet_balance)->toBe(0.0);
    expect($m->fresh()->heldEarnings())->toBe('1000.00');

    $this->actingAs($m->user)->get('/member/wallet')->assertInertia(fn ($page) => $page->where('held_earnings', '1000.00'));

    oehPay($m, 5);

    expect($entry->fresh()->status)->toBe('confirmed');
    expect((float) $m->fresh()->wallet_balance)->toBe(1000.0);
    expect($m->fresh()->heldEarnings())->toBe('0.00');
});

test('TEST.md scenario 38: with EMIs 5 and 6 overdue, paying 5 keeps the earning held and paying 6 releases it', function () {
    $m = oehMember('OEH-M2', 'A', 4);
    oehOverdue($m, [5, 6]);
    oehDownlineJoins($m, 'OEH-P2');

    oehPay($m, 5);
    expect((float) $m->fresh()->wallet_balance)->toBe(0.0);
    expect($m->fresh()->heldEarnings())->toBe('1000.00');

    oehPay($m, 6);
    expect((float) $m->fresh()->wallet_balance)->toBe(1000.0);
});

test('TEST.md scenario 38: "Pay All Remaining EMIs" also releases held earnings', function () {
    $m = oehMember('OEH-M3', 'A', 4);
    oehOverdue($m, [5, 6]);
    oehDownlineJoins($m, 'OEH-P3');

    app(ApproveCashPayment::class)(app(InitiateFullEmiPayment::class)($m->fresh(), 'cash'), User::where('role', 'super_admin')->firstOrFail());

    expect((float) $m->fresh()->wallet_balance)->toBe(1000.0);
});

test('TEST.md scenario 38: Pair/Reward waits while an EMI is overdue and pays ₹500 at the first month-end after it is paid', function () {
    $m = oehMember('OEH-PAIR', 'A', 4);
    oehMember('OEH-PAIR-D1', 'E', 0, $m);
    oehMember('OEH-PAIR-D2', 'E', 0, $m);
    foreach (['left', 'right'] as $side) {
        for ($i = 0; $i < 5; $i++) {
            $payment = Payment::create(['member_id' => $m->id, 'type' => 'registration', 'amount' => 1, 'mode' => 'cash', 'status' => 'paid', 'paid_at' => now()]);
            PairEntry::create(['member_id' => $m->id, 'side' => $side, 'metal' => 'silver', 'source_payment_id' => $payment->id, 'status' => 'unused']);
        }
    }
    oehOverdue($m, [5]);

    app(EvaluatePairMilestones::class)($m, Carbon::create(2026, 9, 30));
    expect(PairRewardTransaction::where('member_id', $m->id)->count())->toBe(0);
    expect(PairEntry::where('member_id', $m->id)->where('status', 'unused')->count())->toBe(10);

    oehPay($m, 5);
    app(EvaluatePairMilestones::class)($m->fresh(), Carbon::create(2026, 10, 31));
    expect((float) PairRewardTransaction::where('member_id', $m->id)->value('reward_amount'))->toBe(500.0);
    expect((float) $m->fresh()->wallet_balance)->toBe(500.0);
});

test('TEST.md scenario 38: a Booster month due while an EMI is overdue stays pending and is paid on the first run after the EMI is paid', function () {
    $m = oehMember('OEH-BST', 'A', 4);
    oehOverdue($m, [5]);
    $qualification = BoosterQualification::create(['member_id' => $m->id, 'level_no' => 1, 'qualified_at' => now()->subMonth(), 'rule_version_id' => DB::table('rule_versions')->where('is_active', true)->value('id')]);
    $month2 = BoosterPayoutSchedule::create(['booster_qualification_id' => $qualification->id, 'month_no' => 2, 'scheduled_date' => now()->toDateString(), 'amount' => 5000, 'status' => 'pending']);

    app(ProcessBoosterPayouts::class)->handle(app(WalletLedgerService::class));
    expect($month2->fresh()->status)->toBe('pending');
    expect((float) $m->fresh()->wallet_balance)->toBe(0.0);

    oehPay($m, 5);
    app(ProcessBoosterPayouts::class)->handle(app(WalletLedgerService::class));
    expect($month2->fresh()->status)->toBe('paid');
    expect((float) $m->fresh()->wallet_balance)->toBe(5000.0);
});

test('a one-time plan member, and a member whose EMI is only due (not overdue), are credited at once', function () {
    $oneTime = oehMember('OEH-E', 'E');
    oehDownlineJoins($oneTime, 'OEH-E-P');
    expect((float) $oneTime->fresh()->wallet_balance)->toBe(1000.0);

    $due = oehMember('OEH-DUE', 'A', 4);
    EmiInstallment::where('emi_schedule_id', $due->emiSchedule->id)->where('installment_no', 5)->update(['status' => 'due']);
    oehDownlineJoins($due, 'OEH-DUE-P');
    expect((float) $due->fresh()->wallet_balance)->toBe(1000.0);
});

test('earnings:verify accepts a held credit while the member is overdue, and warns once it should have been released', function () {
    $m = oehMember('OEH-VER', 'A', 4);
    oehOverdue($m, [5]);
    oehDownlineJoins($m, 'OEH-VER-P');

    $report = app(EarningsVerifier::class)->run(['ledger', 'wallet']);
    expect($report['errors'])->toBe(0);
    expect($report['warnings'])->toBe(0);

    // The EMI is marked paid behind the release's back: the held credit is now stale.
    EmiInstallment::where('emi_schedule_id', $m->emiSchedule->id)->where('installment_no', 5)->update(['status' => 'paid']);
    $ledger = app(EarningsVerifier::class)->run(['ledger'])['checks']['ledger'];
    expect($ledger['errors'])->toBe(0);
    expect(implode(' | ', array_column($ledger['findings'], 'message')))->toContain('is still held, but member');
});

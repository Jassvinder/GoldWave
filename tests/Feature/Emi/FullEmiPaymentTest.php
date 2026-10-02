<?php

use App\Actions\Emi\BookCurrentRate;
use App\Actions\Emi\QuoteCurrentRateBooking;
use App\Actions\Emi\QuoteFullEmiPayment;
use App\Actions\Emi\RequestCurrentRateBooking;
use App\Actions\Payments\ApproveCashPayment;
use App\Actions\Payments\InitiateEmiInstallmentPayment;
use App\Actions\Payments\InitiateFullEmiPayment;
use App\Actions\Payments\RejectCashPayment;
use App\Models\EmiInstallment;
use App\Models\EmiSchedule;
use App\Models\IncomeLedgerCalculation;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\PairEntry;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * T-184 (30-09-2026) — Docs/TEST.md scenario 37: "Pay All Remaining EMIs". Future Rate pays the unpaid EMIs as they
 * stand; Current Rate pays only their principal (no maintenance). One payment, every unpaid installment linked to it,
 * Level Income on the full amount, and the guards around pending payments and booking requests.
 */
function fepMember(string $customerId, string $planCode, int $paid, ?Member $sponsor = null): Member
{
    $plan = MembershipPlan::where('code', $planCode)->firstOrFail();

    $member = Member::create([
        'user_id' => User::factory()->create(['role' => 'member'])->id,
        'customer_id' => $customerId,
        'sponsor_id' => $sponsor?->id,
        'placement_parent_id' => $sponsor?->id,
        'placement_side' => $sponsor ? 'left' : null,
        'membership_plan_id' => $plan->id,
        'status' => 'active',
        'activated_at' => now(),
    ]);

    if (! $plan->isEmiPlan()) {
        return $member;
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
            'due_date' => now()->addMonthsNoOverflow($no - 1)->toDateString(),
            'amount' => $plan->amount,
            'status' => $no <= $paid ? 'paid' : ($no === $paid + 1 ? 'due' : 'upcoming'),
        ]);
    }

    return $member->fresh();
}

function fepOperator(): User
{
    return User::where('role', 'super_admin')->firstOrFail();
}

function fepBook(Member $member): void
{
    $quote = app(QuoteCurrentRateBooking::class)($member);
    app(BookCurrentRate::class)($member, $quote['metal_rate_id'], $quote['paid_installments']);
}

/** Pays the next unpaid EMI on its own (cash, approved), the normal way. */
function fepPayNext(Member $member): void
{
    $next = EmiInstallment::where('emi_schedule_id', $member->emiSchedule->id)->where('status', '!=', 'paid')->orderBy('installment_no')->firstOrFail();
    $next->update(['status' => 'due']);
    app(ApproveCashPayment::class)(app(InitiateEmiInstallmentPayment::class)($member->fresh(), $next, 'cash'), fepOperator());
}

beforeEach(function () {
    $this->seed(); // silver ₹350/g, gold ₹6,000/g
    DB::table('rule_values')->where('key', 'level_income_min_directs')->update(['value' => json_encode([])]);
});

test('TEST.md scenario 37 (Future Rate): 16 unpaid EMIs are paid as one ₹16,000 payment, Level Income is paid on ₹16,000 and Pair entries are created once', function () {
    $s3 = fepMember('FEP-S3', 'E', 0);
    $s2 = fepMember('FEP-S2', 'E', 0, $s3);
    $s1 = fepMember('FEP-S1', 'E', 0, $s2);
    $member = fepMember('FEP-A', 'A', 4, $s1);
    EmiInstallment::where('emi_schedule_id', $member->emiSchedule->id)->where('installment_no', 5)->update(['status' => 'overdue']);

    $quote = app(QuoteFullEmiPayment::class)($member);
    expect($quote['amount'])->toBe(16000.0);
    expect($quote['installments'])->toHaveCount(16);
    expect($quote['saving'])->toBe(0.0);

    $payment = app(InitiateFullEmiPayment::class)($member, 'cash');
    expect((float) $payment->amount)->toBe(16000.0);
    expect($payment->covers_installments)->toBe(16);
    expect($payment->type)->toBe('emi_installment');
    expect(EmiInstallment::where('payment_id', $payment->id)->pluck('installment_no')->all())->toBe(range(5, 20));
    expect(PairEntry::count())->toBe(0);

    app(ApproveCashPayment::class)($payment, fepOperator());

    $installments = $member->emiSchedule->installments()->orderBy('installment_no')->get();
    expect($installments->every(fn ($i) => $i->status === 'paid'))->toBeTrue();
    expect($installments->pluck('amount')->map(fn ($a) => (float) $a)->unique()->all())->toBe([1000.0]);

    $level = IncomeLedgerCalculation::where('source_payment_id', $payment->id)->where('type', 'level_income')->get()->keyBy('level_no');
    expect($level)->toHaveCount(12);
    expect((float) $level[1]->amount)->toBe(800.0);
    expect((float) $level[2]->amount)->toBe(320.0);
    expect((float) $level[3]->amount)->toBe(160.0);
    expect($level[1]->beneficiary_member_id)->toBe($s1->id);

    // 4 → 20 paid EMIs crosses Plan A's 6: this one payment creates the member's Pair entries now — one per Binary
    // ancestor (S1, S2, S3), all from this payment, and never again.
    $entries = PairEntry::whereHas('sourcePayment', fn ($q) => $q->where('member_id', $member->id))->get();
    expect($entries)->toHaveCount(3);
    expect($entries->pluck('source_payment_id')->unique()->all())->toBe([$payment->id]);

    // Approving again changes nothing.
    app(ApproveCashPayment::class)($payment->fresh(), fepOperator());
    expect(IncomeLedgerCalculation::where('source_payment_id', $payment->id)->count())->toBe(12);
});

test('TEST.md scenario 37 (Current Rate): after 2 single EMIs the full payment is ₹30,000 principal (not ₹31,050), rows become ₹5,000 and Gold L1 is ₹600', function () {
    $sponsor = fepMember('FEP-D-S', 'E', 0);
    $member = fepMember('FEP-D', 'D', 2, $sponsor);
    fepBook($member);
    fepPayNext($member); // EMI 3 — ₹5,400
    fepPayNext($member); // EMI 4 — ₹5,350

    $quote = app(QuoteFullEmiPayment::class)($member->fresh());
    expect($quote['amount'])->toBe(30000.0);
    expect($quote['regular_total'])->toBe(31050.0);
    expect($quote['saving'])->toBe(1050.0);

    $payment = app(InitiateFullEmiPayment::class)($member->fresh(), 'cash');
    app(ApproveCashPayment::class)($payment, fepOperator());

    $rows = $member->emiSchedule->installments()->orderBy('installment_no')->get();
    expect($rows->pluck('amount')->map(fn ($a) => (float) $a)->all())
        ->toBe([10000.0, 10000.0, 5400.0, 5350.0, 5000.0, 5000.0, 5000.0, 5000.0, 5000.0, 5000.0]);
    expect($rows->every(fn ($i) => $i->status === 'paid'))->toBeTrue();
    expect(round((float) $rows->where('payment_id', $payment->id)->sum('amount'), 2))->toBe(30000.0);

    $level1 = IncomeLedgerCalculation::where('source_payment_id', $payment->id)->where('level_no', 1)->firstOrFail();
    expect((float) $level1->amount)->toBe(600.0); // Gold L1 2% of ₹30,000.
});

test('TEST.md scenario 37 rounding check: Plan A on Current Rate with 1 EMI paid since booking settles for ₹29,062.50 = 15 × ₹1,937.50', function () {
    $member = fepMember('FEP-AR', 'A', 4);
    fepBook($member);
    fepPayNext($member);

    $quote = app(QuoteFullEmiPayment::class)($member->fresh());
    expect($quote['amount'])->toBe(29062.5);
    expect($quote['amounts'])->toBe(array_fill(0, 15, 1937.5));
});

test('a pending single-EMI payment blocks the full payment, and a pending full payment blocks a single one', function () {
    $member = fepMember('FEP-G1', 'A', 4);
    $next = EmiInstallment::where('emi_schedule_id', $member->emiSchedule->id)->where('installment_no', 5)->firstOrFail();
    $single = app(InitiateEmiInstallmentPayment::class)($member, $next, 'cash');

    expect(fn () => app(InitiateFullEmiPayment::class)($member->fresh(), 'cash'))->toThrow(ValidationException::class);

    app(RejectCashPayment::class)($single, fepOperator());
    $full = app(InitiateFullEmiPayment::class)($member->fresh(), 'cash');
    expect($full->covers_installments)->toBe(16);

    expect(fn () => app(InitiateEmiInstallmentPayment::class)($member->fresh(), $next->fresh(), 'cash'))
        ->toThrow(ValidationException::class, 'Your payment for all remaining EMIs is waiting for confirmation.');
});

test('a rejected cash full payment changes no installment, and the member can pay again', function () {
    $member = fepMember('FEP-REJ', 'A', 4);
    $payment = app(InitiateFullEmiPayment::class)($member, 'cash');

    app(RejectCashPayment::class)($payment, fepOperator());

    $unpaid = $member->emiSchedule->installments()->where('status', '!=', 'paid')->get();
    expect($unpaid)->toHaveCount(16);
    expect($unpaid->pluck('amount')->map(fn ($a) => (float) $a)->unique()->all())->toBe([1000.0]);
    expect(IncomeLedgerCalculation::where('source_payment_id', $payment->id)->count())->toBe(0);

    fepPayNext($member); // a single EMI works again
    $again = app(InitiateFullEmiPayment::class)($member->fresh(), 'cash');
    expect((float) $again->amount)->toBe(15000.0);
});

test('a pending Current Rate booking request is cancelled when a full payment is started', function () {
    $member = fepMember('FEP-REQ', 'A', 4);
    $request = app(RequestCurrentRateBooking::class)($member);

    app(InitiateFullEmiPayment::class)($member->fresh(), 'cash');

    $request = $request->fresh();
    expect($request->status)->toBe('cancelled');
    expect($request->cancel_message)->toBe(InitiateFullEmiPayment::BOOKING_CANCEL_MESSAGE);
});

test('nothing left to pay, or a one-time plan, has no full payment', function () {
    $paidUp = fepMember('FEP-DONE', 'D', 10);
    expect(fn () => app(QuoteFullEmiPayment::class)($paidUp))->toThrow(ValidationException::class);

    $oneTime = fepMember('FEP-E', 'E', 0);
    expect(fn () => app(QuoteFullEmiPayment::class)($oneTime))->toThrow(ValidationException::class);

    $this->actingAs($oneTime->user)->get('/member/emi')
        ->assertInertia(fn ($page) => $page->where('full_payment', null));
    $this->actingAs($paidUp->user)->get('/member/emi')
        ->assertInertia(fn ($page) => $page->where('full_payment', null));
});

test('the EMI page offers the full payment (or says why not), and the member can pay all by cash from it', function () {
    $member = fepMember('FEP-HTTP', 'A', 4);

    $this->actingAs($member->user)->get('/member/emi')
        ->assertInertia(fn ($page) => $page
            ->where('full_payment.count', 16)
            ->where('full_payment.amount', 16000)
            ->where('full_payment.blocked_reason', null));

    $this->actingAs($member->user)->post('/member/emi/pay-all', ['mode' => 'cash'])
        ->assertRedirect('/member/emi');

    $payment = Payment::where('member_id', $member->id)->where('covers_installments', 16)->firstOrFail();
    expect($payment->status)->toBe('pending');

    $this->actingAs($member->user)->get('/member/emi')
        ->assertInertia(fn ($page) => $page
            ->where('full_payment.amount', null)
            ->where('full_payment.blocked_reason', 'A payment is still waiting for confirmation. Pay all remaining EMIs once it is confirmed or rejected.'));
});

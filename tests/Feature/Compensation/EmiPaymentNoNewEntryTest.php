<?php

use App\Actions\Compensation\EvaluateBoosterQualification;
use App\Actions\Payments\ApproveCashPayment;
use App\Actions\Payments\InitiateEmiInstallmentPayment;
use App\Actions\Registration\RegisterMember;
use App\Models\BoosterQualification;
use App\Models\EmiInstallment;
use App\Models\IncomeLedgerCalculation;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\PairEntry;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * T-181 (30-09-2026, user point "EMI-2") — every EMI payment distributes income, but is never a new entry. Confirms,
 * through the real registration and EMI payment flow, what DOMAIN_LOGIC.md §5/§7.3/§9 already say: each installment
 * creates its own Level Income, a Plan A member creates Pair entries exactly once (at the 6th paid EMI), and an EMI
 * installment never triggers a Booster evaluation.
 */
beforeEach(function () {
    $this->seed();
    // This test is about entries, not the T-179 Level Income directs gate.
    DB::table('rule_values')->where('key', 'level_income_min_directs')->update(['value' => json_encode([])]);
});

test('T-181: each EMI payment creates its own Level Income, but Pair entries appear only once (6th EMI) and Booster never re-evaluates on an installment', function () {
    $operator = User::where('role', 'super_admin')->firstOrFail();
    $sponsor = Member::create([
        'user_id' => User::factory()->create(['role' => 'member'])->id,
        'customer_id' => 'EMI2-SPONSOR',
        'status' => 'active',
        'activated_at' => now(),
    ]);

    $member = app(RegisterMember::class)([
        'sponsor_code' => $sponsor->customer_id,
        'placement_side' => 'left',
        'gender' => 'male',
        'name' => 'EMI Member',
        'email' => 'emi2@example.test',
        'mobile' => '9555500001',
        'membership_plan_id' => MembershipPlan::where('code', 'A')->value('id'),
        'payment_mode' => 'cash',
    ]);
    app(ApproveCashPayment::class)($member->payments()->where('type', 'registration')->firstOrFail(), $operator);
    $member = $member->fresh();

    // Pay installments 2..8 in order (installment 1 is the registration payment).
    for ($no = 2; $no <= 8; $no++) {
        $installment = EmiInstallment::where('emi_schedule_id', $member->emiSchedule->id)->where('installment_no', $no)->firstOrFail();
        $installment->update(['status' => 'due']);
        app(ApproveCashPayment::class)(app(InitiateEmiInstallmentPayment::class)($member->fresh(), $installment, 'cash'), $operator);

        $pairEntries = PairEntry::whereHas('sourcePayment', fn ($q) => $q->where('member_id', $member->id))->count();
        expect($pairEntries)->toBe($no >= 6 ? 1 : 0); // One entry for the sponsor at the 6th EMI, never another.
    }

    $payments = Payment::where('member_id', $member->id)->where('status', 'paid')->pluck('id');
    expect($payments)->toHaveCount(8);

    foreach ($payments as $paymentId) {
        // Every installment distributes Level Income on its own (12 rows each, Level 1 paid to the sponsor).
        expect(IncomeLedgerCalculation::where('source_payment_id', $paymentId)->where('type', 'level_income')->count())->toBe(12);
        expect(IncomeLedgerCalculation::where('source_payment_id', $paymentId)->where('level_no', 1)->value('eligibility_status'))->toBe('paid');
    }

    // Booster is structural: an EMI installment payment never creates a Booster qualification.
    $installmentPayment = Payment::where('member_id', $member->id)->where('type', 'emi_installment')->firstOrFail();
    app(EvaluateBoosterQualification::class)($installmentPayment);
    expect(BoosterQualification::count())->toBe(0);
});

<?php

use App\Actions\Compensation\CalculateLevelIncome;
use App\Jobs\ProcessEmiDueStatuses;
use App\Models\EmiInstallment;
use App\Models\IncomeLedgerCalculation;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use Illuminate\Support\Carbon;

/**
 * DOMAIN_LOGIC.md §6 (Level Income), Docs/TEST.md scenario 1 (full 12-level
 * chain, exact worked numbers) and scenario 8 (EMI installments each trigger
 * their own independent Level Income calculation).
 */
function levelIncomeMember(string $customerId, ?Member $sponsor = null, string $status = 'active'): Member
{
    $user = User::factory()->create(['role' => 'member']);

    return Member::create([
        'user_id' => $user->id,
        'customer_id' => $customerId,
        'sponsor_id' => $sponsor?->id,
        'status' => $status,
        'activated_at' => $status === 'active' ? now() : null,
    ]);
}

/**
 * Builds a chain of $count active members, each sponsoring the next. Returns
 * them in Level Income order — index 0 is the last one created (the direct
 * sponsor of whoever is sponsored by the chain's end, i.e. "Level 1"), index
 * $count-1 is the topmost ancestor ("Level $count"). Sponsor the payer with
 * the LAST element of the raw construction order (before reversal).
 */
function buildSponsorChain(string $prefix, int $count): array
{
    $chain = [];
    $sponsor = null;

    for ($i = 1; $i <= $count; $i++) {
        $sponsor = levelIncomeMember("{$prefix}{$i}", $sponsor);
        $chain[] = $sponsor;
    }

    return array_reverse($chain);
}

function makeRegistrationPayment(Member $member, float $amount): Payment
{
    return Payment::create([
        'member_id' => $member->id,
        'type' => 'registration',
        'amount' => $amount,
        'mode' => 'cash',
        'status' => 'paid',
        'cash_status' => 'approved',
        'paid_at' => now(),
    ]);
}

beforeEach(function () {
    $this->seed();
});

test('a confirmed ₹5,000 payment distributes exactly the TEST.md scenario-1 amounts across a full 12-level chain', function () {
    // A..L (12 sponsors), then the paying member M sponsored by L.
    $chain = buildSponsorChain('LI', 12); // LI1 = A (payer's direct sponsor) .. LI12 = L.
    $payer = levelIncomeMember('LIPAYER', $chain[0]);
    $payment = makeRegistrationPayment($payer, 5000);

    app(CalculateLevelIncome::class)($payment);

    $rows = IncomeLedgerCalculation::where('source_payment_id', $payment->id)
        ->where('type', 'level_income')
        ->orderBy('level_no')
        ->get();

    expect($rows)->toHaveCount(12);
    expect($rows->every(fn ($r) => $r->eligibility_status === 'paid'))->toBeTrue();

    $expected = [
        1 => 250.0, 2 => 100.0, 3 => 100.0,
        4 => 50.0, 5 => 50.0, 6 => 50.0, 7 => 50.0, 8 => 50.0,
        9 => 25.0, 10 => 25.0, 11 => 25.0, 12 => 25.0,
    ];

    foreach ($rows as $row) {
        expect((float) $row->amount)->toBe($expected[$row->level_no]);
        expect($row->beneficiary_member_id)->toBe($chain[$row->level_no - 1]->id);
    }

    expect((float) $rows->sum('amount'))->toBe(800.0);

    // Each beneficiary's wallet was credited exactly their row's amount.
    foreach ($chain as $index => $beneficiary) {
        expect((float) $beneficiary->fresh()->wallet_balance)->toBe($expected[$index + 1]);
    }
});

test('amounts that do not divide evenly round to exactly 2 decimal places, not truncated or left unrounded (Docs/TEST.md Risk-based Coverage table)', function () {
    $chain = buildSponsorChain('ROUND', 12);
    $payer = levelIncomeMember('ROUNDPAYER', $chain[0]);
    // ₹333 × 0.5% (Levels 9-12) = 1.665, which must round to exactly 2dp,
    // not be left at 3dp or truncated to 1.66.
    $payment = makeRegistrationPayment($payer, 333);

    app(CalculateLevelIncome::class)($payment);

    $rows = IncomeLedgerCalculation::where('source_payment_id', $payment->id)->orderBy('level_no')->get();

    $level9to12 = $rows->whereIn('level_no', [9, 10, 11, 12]);
    expect($level9to12->pluck('amount')->map(fn ($v) => (float) $v)->unique()->values()->all())->toBe([1.67]);
});

test('a chain shorter than 12 records skipped rows for the unreachable levels, not silently missing rows', function () {
    $chain = buildSponsorChain('SHORT', 5); // only 5 sponsors exist above the payer.
    $payer = levelIncomeMember('SHORTPAYER', $chain[0]);
    $payment = makeRegistrationPayment($payer, 5000);

    app(CalculateLevelIncome::class)($payment);

    $rows = IncomeLedgerCalculation::where('source_payment_id', $payment->id)->orderBy('level_no')->get();
    expect($rows)->toHaveCount(12);

    foreach ($rows as $row) {
        if ($row->level_no <= 5) {
            expect($row->eligibility_status)->toBe('paid');
            expect($row->beneficiary_member_id)->not->toBeNull();
        } else {
            expect($row->eligibility_status)->toBe('skipped');
            expect($row->skip_reason)->toBe('chain_too_short');
            expect($row->beneficiary_member_id)->toBeNull();
            expect((float) $row->amount)->toBe(0.0);
        }
    }
});

test('Level Income always resolves via the Sponsor chain, never the Binary Position chain, even when they diverge (Docs/TEST.md Risk-based Coverage table)', function () {
    // A decoy member with no Sponsor/Direct relationship whatsoever to the
    // paying chain — only a Binary Position (placement) link to B. If
    // Level Income ever mistakenly walked placement_parent_id instead of
    // sponsor_id, this decoy would incorrectly receive a paid level.
    $decoy = levelIncomeMember('DIVERGE-DECOY');

    $a = levelIncomeMember('DIVERGE-A');
    $b = levelIncomeMember('DIVERGE-B', $a);
    $b->update(['placement_parent_id' => $decoy->id, 'placement_side' => 'left']);

    $payer = levelIncomeMember('DIVERGE-PAYER', $b);
    $payment = makeRegistrationPayment($payer, 5000);

    app(CalculateLevelIncome::class)($payment);

    $rows = IncomeLedgerCalculation::where('source_payment_id', $payment->id)->orderBy('level_no')->get();
    expect($rows->firstWhere('level_no', 1)->beneficiary_member_id)->toBe($b->id);
    expect($rows->firstWhere('level_no', 2)->beneficiary_member_id)->toBe($a->id);
    expect($rows->pluck('beneficiary_member_id'))->not->toContain($decoy->id);
    expect((float) $decoy->fresh()->wallet_balance)->toBe(0.0);
});

test('an inactive upline beneficiary is skipped for auditability, other levels are unaffected', function () {
    $chain = buildSponsorChain('INACT', 4);
    // Level 2 (the payer's sponsor's sponsor) is not currently active.
    $chain[1]->update(['status' => 'cancelled']);
    $payer = levelIncomeMember('INACTPAYER', $chain[0]);
    $payment = makeRegistrationPayment($payer, 5000);

    app(CalculateLevelIncome::class)($payment);

    $level1 = IncomeLedgerCalculation::where('source_payment_id', $payment->id)->where('level_no', 1)->firstOrFail();
    $level2 = IncomeLedgerCalculation::where('source_payment_id', $payment->id)->where('level_no', 2)->firstOrFail();
    $level3 = IncomeLedgerCalculation::where('source_payment_id', $payment->id)->where('level_no', 3)->firstOrFail();

    expect($level1->eligibility_status)->toBe('paid');
    expect($level2->eligibility_status)->toBe('skipped');
    expect($level2->skip_reason)->toBe('upline_inactive');
    expect($level2->beneficiary_member_id)->toBe($chain[1]->id); // still records who was skipped.
    expect((float) $level2->amount)->toBe(0.0);
    expect($level3->eligibility_status)->toBe('paid');

    expect((float) $chain[1]->fresh()->wallet_balance)->toBe(0.0);
});

test('wallet ledger entries are created for each paid level, linked back to the income calculation row', function () {
    $chain = buildSponsorChain('WL', 2);
    $payer = levelIncomeMember('WLPAYER', $chain[0]);
    $payment = makeRegistrationPayment($payer, 5000);

    app(CalculateLevelIncome::class)($payment);

    $level1Calc = IncomeLedgerCalculation::where('source_payment_id', $payment->id)->where('level_no', 1)->firstOrFail();
    $entry = WalletLedgerEntry::where('member_id', $chain[0]->id)->firstOrFail();

    expect($entry->entry_type)->toBe('credit');
    expect($entry->category)->toBe('level_income');
    expect((float) $entry->amount)->toBe(250.0);
    expect($entry->source_type)->toBe($level1Calc->getMorphClass());
    expect($entry->source_id)->toBe($level1Calc->id);
    expect((float) $chain[0]->fresh()->wallet_balance)->toBe(250.0);
});

test('calculating twice for the same payment never duplicates rows or double-credits the wallet', function () {
    $chain = buildSponsorChain('IDEMP', 2);
    $payer = levelIncomeMember('IDEMPPAYER', $chain[0]);
    $payment = makeRegistrationPayment($payer, 5000);

    app(CalculateLevelIncome::class)($payment);
    app(CalculateLevelIncome::class)($payment);

    expect(IncomeLedgerCalculation::where('source_payment_id', $payment->id)->count())->toBe(12);
    expect((float) $chain[0]->fresh()->wallet_balance)->toBe(250.0);
});

test('registering via cash triggers Level Income automatically through PaymentConfirmed, and a later EMI installment triggers its own independent calculation', function () {
    $sponsor = levelIncomeMember('E2ESPONSOR');
    $plan = MembershipPlan::where('code', 'A')->firstOrFail();

    $this->post('/join', [
        'sponsor_code' => $sponsor->customer_id,
        'placement_side' => 'left',
        'name' => 'Level Income E2E Member',
        'email' => 'level-income-e2e@example.test',
        'mobile' => '9876520001',
        'membership_plan_id' => $plan->id,
        'rate_booking_method' => 'current_rate',
        'payment_mode' => 'cash',
    ])->assertRedirect();

    $member = Member::whereHas('user', fn ($q) => $q->where('email', 'level-income-e2e@example.test'))->firstOrFail();
    $registrationPayment = Payment::where('member_id', $member->id)->where('type', 'registration')->firstOrFail();
    $superAdmin = User::where('role', 'super_admin')->firstOrFail();

    $this->actingAs($superAdmin)
        ->post("/super-admin/cash-payments/{$registrationPayment->id}/approve")
        ->assertRedirect();

    expect(IncomeLedgerCalculation::where('source_payment_id', $registrationPayment->id)->where('level_no', 1)->exists())->toBeTrue();
    $sponsorBalanceAfterRegistration = (float) $sponsor->fresh()->wallet_balance;
    expect($sponsorBalanceAfterRegistration)->toBeGreaterThan(0.0);

    // Installment #1 is the registration payment itself (T-005) — already calculated above.
    // Manually confirm a second EMI installment payment to prove independence (scenario 8).
    $schedule = $member->emiSchedule()->firstOrFail();
    $installment2 = EmiInstallment::where('emi_schedule_id', $schedule->id)->where('installment_no', 2)->firstOrFail();

    Carbon::setTestNow(now()->addMonth());
    (new ProcessEmiDueStatuses)->handle();

    $this->actingAs($member->user)
        ->post("/member/emi/{$installment2->id}/pay", ['mode' => 'cash'])
        ->assertRedirect();

    $installmentPayment = Payment::find($installment2->fresh()->payment_id);
    $this->actingAs($superAdmin)
        ->post("/super-admin/cash-payments/{$installmentPayment->id}/approve")
        ->assertRedirect();

    expect(IncomeLedgerCalculation::where('source_payment_id', $installmentPayment->id)->where('level_no', 1)->exists())->toBeTrue();
    expect((float) $sponsor->fresh()->wallet_balance)->toBeGreaterThan($sponsorBalanceAfterRegistration);

    Carbon::setTestNow();
});

<?php

use App\Actions\Compensation\CalculateLevelIncome;
use App\Actions\Compensation\ReleaseHeldLevelIncome;
use App\Actions\Payments\ApproveCashPayment;
use App\Actions\Registration\RegisterMember;
use App\Actions\Settings\PublishRuleVersion;
use App\Jobs\ProcessEmiDueStatuses;
use App\Models\EmiInstallment;
use App\Models\EmiSchedule;
use App\Models\IncomeLedgerCalculation;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\RuleValue;
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
        // T-110 (19-09-2026) — CalculateLevelIncome now reads the payer's
        // plan metal (Gold/Silver split); Plan A is Silver, matching every
        // existing worked-number assertion in this file, which was computed
        // against the unsuffixed (now "Silver") rate table.
        'membership_plan_id' => MembershipPlan::where('code', 'A')->firstOrFail()->id,
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

/** T-179 — sets `level_income_min_directs` on the active (seeded) rule version; `[]` = no directs condition. */
function setLevelMinDirects(array $perLevel): void
{
    RuleValue::where('key', 'level_income_min_directs')->update(['value' => $perLevel]);
}

/** A Plan E (one-time) member whose registration is paid — a qualified direct for T-179. */
function qualifiedLevelMember(string $customerId, ?Member $sponsor = null, string $status = 'active'): Member
{
    $member = levelIncomeMember($customerId, $sponsor, $status);
    $member->update(['membership_plan_id' => MembershipPlan::where('code', 'E')->firstOrFail()->id]);
    makeRegistrationPayment($member, 20000);

    return $member->fresh();
}

beforeEach(function () {
    $this->seed();
    // The tests below are about rates, chain resolution and skips, not the T-179 directs gate: switch the gate off
    // (the scenario-36 tests switch it back on).
    setLevelMinDirects([]);
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
        // Level 3 is 1% since 29-09-2026 (seeded defaults = Super Admin's rule version 5).
        1 => 250.0, 2 => 100.0, 3 => 50.0,
        4 => 50.0, 5 => 50.0, 6 => 50.0, 7 => 50.0, 8 => 50.0,
        9 => 25.0, 10 => 25.0, 11 => 25.0, 12 => 25.0,
    ];

    foreach ($rows as $row) {
        expect((float) $row->amount)->toBe($expected[$row->level_no]);
        expect($row->beneficiary_member_id)->toBe($chain[$row->level_no - 1]->id);
    }

    expect((float) $rows->sum('amount'))->toBe(750.0);

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

test('T-110: a Gold-plan payer\'s payment uses the Gold rate table, never the Silver one', function () {
    RuleValue::where('key', 'level_income_rates')->update(['value' => ['1' => 5]]);
    RuleValue::where('key', 'level_income_rates_gold')->update(['value' => ['1' => 8]]);

    $goldPlan = MembershipPlan::where('code', 'C')->firstOrFail(); // Gold.
    $sponsor = levelIncomeMember('GOLDLI-SPONSOR');
    $payer = levelIncomeMember('GOLDLI-PAYER', $sponsor);
    $payer->update(['membership_plan_id' => $goldPlan->id]);

    $payment = makeRegistrationPayment($payer, 5000);
    app(CalculateLevelIncome::class)($payment);

    $row = IncomeLedgerCalculation::where('source_payment_id', $payment->id)->where('level_no', 1)->firstOrFail();
    expect((float) $row->rate_percent)->toBe(8.0);
    expect((float) $row->amount)->toBe(400.0); // 5000 * 8%, not 5000 * 5%.
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
        'gender' => 'male',
        'name' => 'Level Income E2E Member',
        'email' => 'level-income-e2e@example.test',
        'mobile' => '9876520001',
        'membership_plan_id' => $plan->id,
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

test('TEST.md scenario 36: each level needs 2 more qualified directs — short of them the level is held and released once the directs are met', function () {
    setLevelMinDirects(['1' => 2, '2' => 4, '3' => 6, '4' => 8, '5' => 10, '6' => 12, '7' => 14, '8' => 16, '9' => 18, '10' => 20, '11' => 22, '12' => 24]);

    $s3 = qualifiedLevelMember('SC36-S3');
    $s2 = qualifiedLevelMember('SC36-S2', $s3);
    $s1 = qualifiedLevelMember('SC36-S1', $s2);
    qualifiedLevelMember('SC36-S1-X1', $s1);        // S1: P + 1 = 2 directs.
    foreach ([1, 2] as $i) {
        qualifiedLevelMember("SC36-S2-X{$i}", $s2); // S2: S1 + 2 = 3 directs.
    }
    foreach ([1, 2, 3, 4, 5] as $i) {
        qualifiedLevelMember("SC36-S3-X{$i}", $s3); // S3: S2 + 5 = 6 directs.
    }
    $payer = qualifiedLevelMember('SC36-P', $s1);
    $payment = Payment::where('member_id', $payer->id)->firstOrFail(); // ₹20,000 Plan E registration.

    app(CalculateLevelIncome::class)($payment);

    $rows = IncomeLedgerCalculation::where('source_payment_id', $payment->id)->orderBy('level_no')->get()->keyBy('level_no');
    expect($rows[1]->eligibility_status)->toBe('paid');
    expect((float) $rows[1]->amount)->toBe(1000.0);              // 5% of 20,000; 2 >= 2.
    expect($rows[2]->eligibility_status)->toBe('held');
    expect($rows[2]->skip_reason)->toBe('insufficient_directs'); // 3 < 4.
    expect($rows[2]->beneficiary_member_id)->toBe($s2->id);
    expect((float) $rows[2]->amount)->toBe(400.0);               // 2% of 20,000, fixed now, not in the wallet yet.
    expect($rows[3]->eligibility_status)->toBe('paid');
    expect((float) $rows[3]->amount)->toBe(200.0);               // 1%; 6 >= 6.
    expect($rows[4]->skip_reason)->toBe('chain_too_short');
    expect((float) $s1->fresh()->wallet_balance)->toBe(1000.0);
    expect((float) $s2->fresh()->wallet_balance)->toBe(0.0);
    expect(WalletLedgerEntry::where('member_id', $s2->id)->exists())->toBeFalse();

    // Nothing to release while S2 is still at 3.
    expect(app(ReleaseHeldLevelIncome::class)($s2->fresh()))->toBe(0.0);

    // S2 gets a 4th qualified direct: the held ₹400 is released once, into the wallet.
    qualifiedLevelMember('SC36-S2-X3', $s2);
    expect(app(ReleaseHeldLevelIncome::class)($s2->fresh()))->toBe(400.0);
    expect(app(ReleaseHeldLevelIncome::class)($s2->fresh()))->toBe(0.0); // Idempotent.

    $released = $rows[2]->fresh();
    expect($released->eligibility_status)->toBe('paid');
    expect($released->skip_reason)->toBeNull();
    expect($released->released_at)->not->toBeNull();
    expect((float) $released->amount)->toBe(400.0);
    expect((float) $s2->fresh()->wallet_balance)->toBe(400.0);
    expect(WalletLedgerEntry::where('member_id', $s2->id)->where('source_id', $released->id)->value('status'))->toBe('confirmed');
});

test('T-186: each held level is released on its own count, and an inactive beneficiary keeps it held', function () {
    setLevelMinDirects(['1' => 2, '2' => 4]);

    $s2 = qualifiedLevelMember('HLD-S2');
    $s1 = qualifiedLevelMember('HLD-S1', $s2);       // S2: 1 direct.
    $payer = qualifiedLevelMember('HLD-P', $s1);     // S1: 1 direct.
    app(CalculateLevelIncome::class)(Payment::where('member_id', $payer->id)->firstOrFail());

    // S2 reaches 2 directs: that meets L1 only, and S2's held row is L2 (needs 4) — stays held.
    qualifiedLevelMember('HLD-S2-X1', $s2);
    expect(app(ReleaseHeldLevelIncome::class)($s2->fresh()))->toBe(0.0);

    // S1 reaches 2 but is not active: stays held until S1 is active again.
    qualifiedLevelMember('HLD-S1-X1', $s1);
    $s1->update(['status' => 'cancelled']);
    expect(app(ReleaseHeldLevelIncome::class)($s1->fresh()))->toBe(0.0);
    $s1->update(['status' => 'active']);
    expect(app(ReleaseHeldLevelIncome::class)($s1->fresh()))->toBe(1000.0);
});

test('T-186: a direct confirmed through the real flow releases the sponsor\'s held income, and lowering the rule releases the rest', function () {
    setLevelMinDirects(['1' => 2, '2' => 4]);

    $s2 = qualifiedLevelMember('FLW-S2');
    $s1 = qualifiedLevelMember('FLW-S1', $s2);
    $payer = qualifiedLevelMember('FLW-P', $s1);
    app(CalculateLevelIncome::class)(Payment::where('member_id', $payer->id)->firstOrFail());
    expect(IncomeLedgerCalculation::where('eligibility_status', 'held')->count())->toBe(2); // S1 L1 ₹1,000, S2 L2 ₹400.

    // A second direct of S1 registers and its cash payment is approved: the listener releases S1's L1 at once.
    $second = app(RegisterMember::class)([
        'sponsor_code' => $s1->customer_id, 'placement_side' => 'right', 'gender' => 'male', 'name' => 'Second Direct',
        'email' => 'flw-second@example.test', 'mobile' => '9555500002',
        'membership_plan_id' => MembershipPlan::where('code', 'E')->value('id'), 'payment_mode' => 'cash',
    ]);
    app(ApproveCashPayment::class)($second->payments()->firstOrFail(), User::where('role', 'super_admin')->firstOrFail());
    expect((float) $s1->fresh()->wallet_balance)->toBe(1000.0 + 1000.0); // Released ₹1,000 + L1 on the new payment.

    // Super Admin lowers L2 to 1 direct: publishing releases S2's held L2.
    app(PublishRuleVersion::class)(['level_income_min_directs' => ['1' => 1, '2' => 1]], User::where('role', 'super_admin')->firstOrFail());
    expect(IncomeLedgerCalculation::where('eligibility_status', 'held')->count())->toBe(0);
    expect((float) $s2->fresh()->wallet_balance)->toBe(800.0); // Both held L2 rows: P's ₹400 and the second direct's ₹400.
});

test('TEST.md scenario 36: an EMI direct below its Pair qualification EMIs and a non-active direct do not count', function () {
    setLevelMinDirects(['1' => 2]);

    $s1 = qualifiedLevelMember('SC36E-S1');
    qualifiedLevelMember('SC36E-CANCELLED', $s1, 'cancelled');
    $emiDirect = levelIncomeMember('SC36E-EMI', $s1); // Plan A — needs 6 paid EMIs.
    $schedule = EmiSchedule::create([
        'member_id' => $emiDirect->id,
        'membership_plan_id' => $emiDirect->membership_plan_id,
        'total_installments' => 20,
        'rate_booking_method' => 'future_rate',
        'installment_amount' => 1000,
    ]);
    foreach (range(1, 5) as $no) {
        EmiInstallment::create(['emi_schedule_id' => $schedule->id, 'installment_no' => $no, 'due_date' => now(), 'amount' => 1000, 'status' => 'paid']);
    }

    // S1's directs: the cancelled one, the 5-EMI Plan A one and the payer itself (Plan E, paid) = 1 qualified.
    $payer = qualifiedLevelMember('SC36E-P', $s1);
    $payment = Payment::where('member_id', $payer->id)->firstOrFail();
    app(CalculateLevelIncome::class)($payment);

    $level1 = IncomeLedgerCalculation::where('source_payment_id', $payment->id)->where('level_no', 1)->firstOrFail();
    expect($level1->eligibility_status)->toBe('held');
    expect($level1->skip_reason)->toBe('insufficient_directs');

    // 6th EMI paid — the Plan A direct now counts, so the next payment pays L1.
    EmiInstallment::create(['emi_schedule_id' => $schedule->id, 'installment_no' => 6, 'due_date' => now(), 'amount' => 1000, 'status' => 'paid']);
    $next = qualifiedLevelMember('SC36E-P2', $s1);
    $nextPayment = Payment::where('member_id', $next->id)->firstOrFail();
    app(CalculateLevelIncome::class)($nextPayment);

    expect(IncomeLedgerCalculation::where('source_payment_id', $nextPayment->id)->where('level_no', 1)->value('eligibility_status'))->toBe('paid');
});

test('T-179: a rule version without level_income_min_directs pays exactly as before', function () {
    RuleValue::where('key', 'level_income_min_directs')->delete();

    $sponsor = levelIncomeMember('NOKEY-SPONSOR'); // Its only direct is the payer (Plan A, not yet qualified).
    $payer = levelIncomeMember('NOKEY-PAYER', $sponsor);
    $payment = makeRegistrationPayment($payer, 5000);
    app(CalculateLevelIncome::class)($payment);

    $level1 = IncomeLedgerCalculation::where('source_payment_id', $payment->id)->where('level_no', 1)->firstOrFail();
    expect($level1->eligibility_status)->toBe('paid');
    expect((float) $level1->amount)->toBe(250.0);
});

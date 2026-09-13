<?php

use App\Actions\Registration\ActivateMembershipOnPaymentConfirmed;
use App\Jobs\ProcessEmiDueStatuses;
use App\Models\EmiInstallment;
use App\Models\EmiSchedule;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\FakePaymentGateway;
use Illuminate\Support\Carbon;

/**
 * DOMAIN_LOGIC.md §5 items 7-8, §10, Docs/TEST.md scenario 12. T-005: full
 * emi_installments schedule generation (activation-date-anniversary due
 * dates, no grace period before overdue) and the recurring installment
 * Payment In flow (online + cash), with the next-due-only ordering rule.
 */
function createActiveSponsor(string $customerId): Member
{
    $user = User::factory()->create(['role' => 'member']);

    return Member::create([
        'user_id' => $user->id,
        'customer_id' => $customerId,
        'status' => 'active',
        'activated_at' => now(),
    ]);
}

function registerPlanAViaCash(string $email, string $mobile): Member
{
    $plan = MembershipPlan::where('code', 'A')->firstOrFail();

    test()->post('/join', [
        'sponsor_code' => 'GWL900',
        'placement_side' => 'left',
        'name' => 'Emi Test Member',
        'email' => $email,
        'mobile' => $mobile,
        'membership_plan_id' => $plan->id,
        'rate_booking_method' => 'current_rate',
        'payment_mode' => 'cash',
    ])->assertRedirect();

    $member = Member::whereHas('user', fn ($q) => $q->where('email', $email))->firstOrFail();
    $payment = Payment::where('member_id', $member->id)->where('type', 'registration')->firstOrFail();
    $superAdmin = User::where('role', 'super_admin')->firstOrFail();

    test()->actingAs($superAdmin)
        ->post("/super-admin/cash-payments/{$payment->id}/approve")
        ->assertRedirect();

    return $member->fresh();
}

function advanceToInstallment2Due(): void
{
    Carbon::setTestNow(Carbon::create(2026, 3, 17)); // installment #2's due date.
    (new ProcessEmiDueStatuses)->handle();
}

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 2, 17));
    $this->seed();
    createActiveSponsor('GWL900');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('activation generates the full installment schedule on activation-date-anniversary due dates, with installment #1 already paid by the registration payment', function () {
    $member = registerPlanAViaCash('schedule@example.test', '9876510001');
    $registrationPayment = Payment::where('member_id', $member->id)->where('type', 'registration')->firstOrFail();
    $schedule = EmiSchedule::where('member_id', $member->id)->firstOrFail();

    $installments = EmiInstallment::where('emi_schedule_id', $schedule->id)->orderBy('installment_no')->get();

    expect($installments)->toHaveCount(20);
    expect((float) $installments->sum('amount'))->toBe(2100.0 * 20);

    $first = $installments->first();
    expect($first->status)->toBe('paid');
    expect($first->payment_id)->toBe($registrationPayment->id);
    expect($first->due_date->toDateString())->toBe('2026-02-17');

    $second = $installments[1];
    expect($second->status)->toBe('upcoming'); // its due date (17-03-2026) hasn't been reached yet.
    expect($second->due_date->toDateString())->toBe('2026-03-17');
    expect($second->payment_id)->toBeNull();

    $third = $installments[2];
    expect($third->status)->toBe('upcoming');
    expect($third->due_date->toDateString())->toBe('2026-04-17');
});

test('re-activation (idempotency) never duplicates the installment schedule', function () {
    $member = registerPlanAViaCash('idempotent@example.test', '9876510002');
    $schedule = EmiSchedule::where('member_id', $member->id)->firstOrFail();

    expect(EmiInstallment::where('emi_schedule_id', $schedule->id)->count())->toBe(20);

    app(ActivateMembershipOnPaymentConfirmed::class)(
        $member,
        null,
        Payment::where('member_id', $member->id)->firstOrFail(),
    );

    expect(EmiInstallment::where('emi_schedule_id', $schedule->id)->count())->toBe(20);
});

test('a member can only pay their single next-due installment, never skip ahead', function () {
    $member = registerPlanAViaCash('ordering@example.test', '9876510003');
    advanceToInstallment2Due();
    $schedule = EmiSchedule::where('member_id', $member->id)->firstOrFail();
    $installment3 = EmiInstallment::where('emi_schedule_id', $schedule->id)->where('installment_no', 3)->firstOrFail();

    // Installment #2 is now 'due', but #3 (still 'upcoming') must not be payable ahead of it.
    $this->actingAs($member->user)
        ->post("/member/emi/{$installment3->id}/pay", ['mode' => 'cash'])
        ->assertSessionHasErrors('installment');

    expect($installment3->fresh()->payment_id)->toBeNull();
});

test('cash Payment In flow confirms installment #2 and advances the next-due installment to #3', function () {
    $member = registerPlanAViaCash('cashflow@example.test', '9876510004');
    advanceToInstallment2Due();
    $schedule = EmiSchedule::where('member_id', $member->id)->firstOrFail();
    $installment2 = EmiInstallment::where('emi_schedule_id', $schedule->id)->where('installment_no', 2)->firstOrFail();

    $this->actingAs($member->user)
        ->post("/member/emi/{$installment2->id}/pay", ['mode' => 'cash'])
        ->assertRedirect(route('member.emi.index'));

    $payment = Payment::find($installment2->fresh()->payment_id);
    expect($payment->type)->toBe('emi_installment');
    expect((float) $payment->amount)->toBe(2100.0);
    expect($payment->cash_status)->toBe('pending_verification');

    $superAdmin = User::where('role', 'super_admin')->firstOrFail();
    $this->actingAs($superAdmin)
        ->post("/super-admin/cash-payments/{$payment->id}/approve")
        ->assertRedirect();

    expect($installment2->fresh()->status)->toBe('paid');

    // Installment #3 isn't due until 17-04-2026 — advance again before it becomes payable.
    Carbon::setTestNow(Carbon::create(2026, 4, 17));
    (new ProcessEmiDueStatuses)->handle();

    $installment3 = EmiInstallment::where('emi_schedule_id', $schedule->id)->where('installment_no', 3)->firstOrFail();
    $this->actingAs($member->user)
        ->post("/member/emi/{$installment3->id}/pay", ['mode' => 'cash'])
        ->assertRedirect(route('member.emi.index'));

    expect($installment3->fresh()->payment_id)->not->toBeNull();
});

test('online Payment In flow confirms installment #2 via the webhook, idempotently', function () {
    $member = registerPlanAViaCash('onlineflow@example.test', '9876510005');
    advanceToInstallment2Due();
    $schedule = EmiSchedule::where('member_id', $member->id)->firstOrFail();
    $installment2 = EmiInstallment::where('emi_schedule_id', $schedule->id)->where('installment_no', 2)->firstOrFail();

    $this->actingAs($member->user)
        ->post("/member/emi/{$installment2->id}/pay", ['mode' => 'online'])
        ->assertRedirect();

    $payment = Payment::find($installment2->fresh()->payment_id);

    $gateway = app(FakePaymentGateway::class);
    $reference = 'FAKE-EMI-REF';
    $signature = $gateway->sign($payment->id, $reference);
    $callback = ['payment_id' => $payment->id, 'reference' => $reference, 'signature' => $signature];

    $this->postJson('/payments/webhook', $callback)->assertOk();
    expect($installment2->fresh()->status)->toBe('paid');

    // Duplicate/retried webhook must not error or double-process.
    $this->postJson('/payments/webhook', $callback)->assertOk();
    expect($installment2->fresh()->status)->toBe('paid');
});

test('a member cannot pay another member\'s installment', function () {
    $member = registerPlanAViaCash('owner@example.test', '9876510006');
    $otherMember = registerPlanAViaCash('intruder@example.test', '9876510007');
    $schedule = EmiSchedule::where('member_id', $member->id)->firstOrFail();
    $installment2 = EmiInstallment::where('emi_schedule_id', $schedule->id)->where('installment_no', 2)->firstOrFail();

    $this->actingAs($otherMember->user)
        ->post("/member/emi/{$installment2->id}/pay", ['mode' => 'cash'])
        ->assertForbidden();
});

test('ProcessEmiDueStatuses transitions upcoming to due and due to overdue with no grace period', function () {
    $member = registerPlanAViaCash('dueprocessor@example.test', '9876510008');
    $schedule = EmiSchedule::where('member_id', $member->id)->firstOrFail();
    $installment2 = EmiInstallment::where('emi_schedule_id', $schedule->id)->where('installment_no', 2)->firstOrFail();
    $installment3 = EmiInstallment::where('emi_schedule_id', $schedule->id)->where('installment_no', 3)->firstOrFail();

    expect($installment2->fresh()->status)->toBe('upcoming');
    expect($installment3->fresh()->status)->toBe('upcoming');

    // On the due date itself: becomes 'due', not yet 'overdue' (no grace period, but not late until the next day).
    Carbon::setTestNow(Carbon::create(2026, 3, 17));
    (new ProcessEmiDueStatuses)->handle();
    expect($installment2->fresh()->status)->toBe('due');
    expect($installment3->fresh()->status)->toBe('upcoming');

    // The very next calendar day, still unpaid: becomes 'overdue' immediately.
    Carbon::setTestNow(Carbon::create(2026, 3, 18));
    (new ProcessEmiDueStatuses)->handle();
    expect($installment2->fresh()->status)->toBe('overdue');

    // Installment #3 (due 17-04-2026) is unaffected by installment #2's lateness.
    expect($installment3->fresh()->status)->toBe('upcoming');
});

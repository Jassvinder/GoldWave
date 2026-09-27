<?php

use App\Actions\Emi\BookCurrentRate;
use App\Actions\Emi\QuoteCurrentRateBooking;
use App\Actions\Emi\RevertCurrentRateBooking;
use App\Models\EmiInstallment;
use App\Models\EmiSchedule;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\MetalRate;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * T-116 (20-09-2026) — Docs/TEST.md scenario 21: booking at the Current Rate after registration. Paid EMIs are credited
 * against the metal value, maintenance is 1% of the remaining value only, and only unpaid installments are re-priced.
 */
function bcrMember(string $customerId, string $planCode, int $paid, string $status = 'active'): Member
{
    $user = User::factory()->create(['role' => 'member']);
    $plan = MembershipPlan::where('code', $planCode)->firstOrFail();

    $member = Member::create([
        'user_id' => $user->id,
        'customer_id' => $customerId,
        'membership_plan_id' => $plan->id,
        'status' => $status,
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

function bcrBook(Member $member): EmiSchedule
{
    $quote = app(QuoteCurrentRateBooking::class)($member);

    return app(BookCurrentRate::class)($member, $quote['metal_rate_id'], $quote['paid_installments']);
}

beforeEach(function () {
    $this->seed(); // silver ₹350/g, gold ₹6,000/g
});

test('Plan A, 4 EMIs paid: the quote credits the ₹4,000 and charges 1% maintenance on the remaining ₹31,000 only', function () {
    $member = bcrMember('BCR-A', 'A', 4);

    $quote = app(QuoteCurrentRateBooking::class)($member);

    expect($quote['fixed_weight_grams'])->toBe(100.0);
    expect($quote['rate_per_gram'])->toBe(350.0);
    expect($quote['total_value'])->toBe(35000.0);
    expect($quote['paid_installments'])->toBe(4);
    expect($quote['paid_amount'])->toBe(4000.0);
    expect($quote['remaining_value'])->toBe(31000.0);
    expect($quote['pending_installments'])->toBe(16);
    expect($quote['maintenance_cost'])->toBe(310.0);
    expect($quote['installment_amount'])->toBe(2247.5);
    expect($quote['total_remaining_payable'])->toBe(35960.0);
});

test('booking re-prices only the unpaid installments, keeps due dates, and records the audit snapshot', function () {
    $member = bcrMember('BCR-A2', 'A', 4);
    $schedule = $member->emiSchedule;
    $dueDatesBefore = $schedule->installments()->orderBy('installment_no')->pluck('due_date')->map->toDateString()->all();

    bcrBook($member);

    $schedule = $schedule->fresh();
    expect($schedule->rate_booking_method)->toBe('current_rate');
    expect((float) $schedule->installment_amount)->toBe(2247.5);
    expect((float) $schedule->rate_per_gram_at_booking)->toBe(350.0);
    expect((float) $schedule->fixed_weight_grams)->toBe(100.0);
    expect((float) $schedule->maintenance_cost)->toBe(310.0);
    expect($schedule->installments_paid_at_booking)->toBe(4);
    expect((float) $schedule->amount_paid_at_booking)->toBe(4000.0);
    expect($schedule->current_rate_booked_at)->not->toBeNull();
    expect($schedule->metal_rate_id)->not->toBeNull();

    $installments = $schedule->installments()->orderBy('installment_no')->get();
    expect($installments->where('status', 'paid')->pluck('amount')->map(fn ($a) => (float) $a)->unique()->all())->toBe([1000.0]);
    expect($installments->where('status', '!=', 'paid')->pluck('amount')->map(fn ($a) => (float) $a)->unique()->all())->toBe([2247.5]);
    expect($installments->where('status', '!=', 'paid'))->toHaveCount(16);
    expect($installments->pluck('due_date')->map->toDateString()->all())->toBe($dueDatesBefore);
});

test('Plan D (gold), 2 EMIs paid: ₹60,000 total, ₹40,000 remaining over 8, ₹400 maintenance, new EMI ₹5,400', function () {
    $member = bcrMember('BCR-D', 'D', 2);

    $quote = app(QuoteCurrentRateBooking::class)($member);

    expect($quote['total_value'])->toBe(60000.0);
    expect($quote['remaining_value'])->toBe(40000.0);
    expect($quote['pending_installments'])->toBe(8);
    expect($quote['maintenance_cost'])->toBe(400.0);
    expect($quote['installment_amount'])->toBe(5400.0);
});

test('a schedule already on Current Rate cannot be booked again and is left untouched', function () {
    $member = bcrMember('BCR-TWICE', 'A', 4);
    bcrBook($member);
    $before = $member->emiSchedule->fresh()->installments()->pluck('amount', 'installment_no')->all();

    expect(fn () => app(QuoteCurrentRateBooking::class)($member->fresh()))->toThrow(ValidationException::class);
    expect(fn () => app(BookCurrentRate::class)($member->fresh(), 1, 4))->toThrow(ValidationException::class);

    expect($member->emiSchedule->fresh()->installments()->pluck('amount', 'installment_no')->all())->toBe($before);
});

test('booking is refused when the paid EMIs already cover the whole metal value', function () {
    $member = bcrMember('BCR-COVERED', 'A', 4); // ₹4,000 paid
    MetalRate::where('metal', 'silver')->update(['rate_per_gram' => 30]); // 100g × ₹30 = ₹3,000

    expect(fn () => app(QuoteCurrentRateBooking::class)($member))->toThrow(ValidationException::class);
    expect($member->emiSchedule->fresh()->rate_booking_method)->toBe('future_rate');
});

test('booking is refused while an installment payment is still awaiting confirmation', function () {
    $member = bcrMember('BCR-PENDING', 'A', 4);
    $installment = $member->emiSchedule->installments()->where('installment_no', 5)->firstOrFail();
    $payment = Payment::create([
        'member_id' => $member->id,
        'type' => 'emi_installment',
        'amount' => 1000,
        'mode' => 'cash',
        'status' => 'pending',
        'idempotency_key' => (string) Str::uuid(),
        'cash_status' => 'pending_verification',
    ]);
    $installment->update(['payment_id' => $payment->id]);

    expect(fn () => app(QuoteCurrentRateBooking::class)($member))->toThrow(ValidationException::class);
});

test('a stale quote — the rate or the paid-EMI count changed since the popup — is rejected', function () {
    $member = bcrMember('BCR-STALE', 'A', 4);
    $quote = app(QuoteCurrentRateBooking::class)($member);

    // Another EMI gets paid after the member opened the popup.
    $member->emiSchedule->installments()->where('installment_no', 5)->update(['status' => 'paid']);

    expect(fn () => app(BookCurrentRate::class)($member->fresh(), $quote['metal_rate_id'], $quote['paid_installments']))
        ->toThrow(ValidationException::class);
    expect($member->emiSchedule->fresh()->rate_booking_method)->toBe('future_rate');

    // A different rate id is stale too.
    expect(fn () => app(BookCurrentRate::class)($member->fresh(), $quote['metal_rate_id'] + 999, 5))
        ->toThrow(ValidationException::class);
});

test('a fully paid schedule, a one-time plan and an inactive member cannot book', function () {
    $paidUp = bcrMember('BCR-DONE', 'D', 10);
    $oneTime = bcrMember('BCR-E', 'E', 0);
    $inactive = bcrMember('BCR-INACTIVE', 'A', 4, 'payment_pending');

    foreach ([$paidUp, $oneTime, $inactive] as $member) {
        expect(fn () => app(QuoteCurrentRateBooking::class)($member))->toThrow(ValidationException::class);
    }
});

test('the Membership page shows no weight on Future Rate, offers the quote, and after booking shows the weight and no button', function () {
    $member = bcrMember('BCR-PAGE', 'A', 4);

    $this->actingAs($member->user)
        ->get('/member/membership')
        ->assertInertia(fn ($page) => $page
            ->component('member/membership')
            ->where('plan.fixed_weight_grams', null)
            ->where('rate_booking.method', 'future_rate')
            ->where('current_rate_quote.installment_amount', 2247.5)
            ->where('current_rate_quote.pending_installments', 16));

    $quote = app(QuoteCurrentRateBooking::class)($member);

    $this->actingAs($member->user)
        ->post('/member/membership/book-current-rate', [
            'metal_rate_id' => $quote['metal_rate_id'],
            'paid_installments' => $quote['paid_installments'],
        ])
        ->assertRedirect(route('member.membership.show'));

    $this->actingAs($member->user)
        ->get('/member/membership')
        ->assertInertia(fn ($page) => $page
            ->where('plan.fixed_weight_grams', '100.000')
            ->where('rate_booking.method', 'current_rate')
            ->where('rate_booking.installment_amount', '2247.50')
            ->where('current_rate_quote', null));
});

test('a rejected booking request over HTTP changes nothing and reports the reason', function () {
    $member = bcrMember('BCR-HTTP', 'A', 4);

    $this->actingAs($member->user)
        ->post('/member/membership/book-current-rate', ['metal_rate_id' => 999999, 'paid_installments' => 4])
        ->assertSessionHasErrors('booking');

    expect($member->emiSchedule->fresh()->rate_booking_method)->toBe('future_rate');
    expect((float) $member->emiSchedule->fresh()->installments()->where('installment_no', 10)->value('amount'))->toBe(1000.0);
});

test('a member only ever books their own schedule', function () {
    $mine = bcrMember('BCR-MINE', 'A', 4);
    $other = bcrMember('BCR-OTHER', 'A', 4);
    $quote = app(QuoteCurrentRateBooking::class)($mine);

    $this->actingAs($mine->user)
        ->post('/member/membership/book-current-rate', [
            'metal_rate_id' => $quote['metal_rate_id'],
            'paid_installments' => $quote['paid_installments'],
        ]);

    expect($mine->emiSchedule->fresh()->rate_booking_method)->toBe('current_rate');
    expect($other->emiSchedule->fresh()->rate_booking_method)->toBe('future_rate');
});

/*
 * Super Admin revert of a Current Rate booking — Docs/TEST.md scenario 22.
 */
function rvtSuperAdmin(): User
{
    return User::where('role', 'super_admin')->firstOrFail();
}

test('a Super Admin revert restores the plain plan amount, clears the booking snapshot and logs both events', function () {
    $member = bcrMember('RVT-A', 'A', 4);
    bcrBook($member);

    app(RevertCurrentRateBooking::class)($member->fresh(), rvtSuperAdmin(), 'Booked by mistake');

    $schedule = $member->emiSchedule->fresh();
    expect($schedule->rate_booking_method)->toBe('future_rate');
    expect((float) $schedule->installment_amount)->toBe(1000.0);
    expect($schedule->metal_rate_id)->toBeNull();
    expect($schedule->rate_per_gram_at_booking)->toBeNull();
    expect($schedule->fixed_weight_grams)->toBeNull();
    expect($schedule->maintenance_cost)->toBeNull();
    expect($schedule->current_rate_booked_at)->toBeNull();
    expect($schedule->installments_paid_at_booking)->toBeNull();
    expect($schedule->amount_paid_at_booking)->toBeNull();

    $installments = $schedule->installments()->get();
    expect($installments->pluck('amount')->map(fn ($a) => (float) $a)->unique()->all())->toBe([1000.0]);
    expect($installments->where('status', 'paid'))->toHaveCount(4);

    $events = $schedule->rateBookingEvents()->orderBy('id')->get();
    expect($events->pluck('event')->all())->toBe(['booked', 'reverted']);
    expect($events[0]->performed_by_user_id)->toBe($member->user_id);
    expect($events[1]->performed_by_user_id)->toBe(rvtSuperAdmin()->id);
    expect($events[1]->reason)->toBe('Booked by mistake');
});

test('after a revert the member can book at the Current Rate again', function () {
    $member = bcrMember('RVT-REBOOK', 'A', 4);
    bcrBook($member);
    app(RevertCurrentRateBooking::class)($member->fresh(), rvtSuperAdmin(), 'Booked by mistake');

    bcrBook($member->fresh());

    expect($member->emiSchedule->fresh()->rate_booking_method)->toBe('current_rate');
    expect($member->emiSchedule->rateBookingEvents()->pluck('event')->all())->toBe(['booked', 'reverted', 'booked']);
});

test('a revert is refused once an EMI was paid after the booking', function () {
    $member = bcrMember('RVT-PAID', 'A', 4);
    bcrBook($member);
    $member->emiSchedule->installments()->where('installment_no', 5)->update(['status' => 'paid']);

    expect(fn () => app(RevertCurrentRateBooking::class)($member->fresh(), rvtSuperAdmin(), 'Too late'))->toThrow(ValidationException::class);

    expect($member->emiSchedule->fresh()->rate_booking_method)->toBe('current_rate');
    expect((float) $member->emiSchedule->installments()->where('installment_no', 10)->value('amount'))->toBe(2247.5);
});

test('a revert is refused while an installment payment is awaiting confirmation', function () {
    $member = bcrMember('RVT-PENDING', 'A', 4);
    bcrBook($member);
    $payment = Payment::create([
        'member_id' => $member->id,
        'type' => 'emi_installment',
        'amount' => 2247.5,
        'mode' => 'cash',
        'status' => 'pending',
        'idempotency_key' => (string) Str::uuid(),
        'cash_status' => 'pending_verification',
    ]);
    $member->emiSchedule->installments()->where('installment_no', 5)->update(['payment_id' => $payment->id]);

    expect(fn () => app(RevertCurrentRateBooking::class)($member->fresh(), rvtSuperAdmin(), 'Booked by mistake'))->toThrow(ValidationException::class);
});

test('a schedule that was Current Rate from registration, or is on Future Rate, cannot be reverted', function () {
    $legacy = bcrMember('RVT-LEGACY', 'A', 4);
    $legacy->emiSchedule->update(['rate_booking_method' => 'current_rate', 'installment_amount' => 2100]);
    $future = bcrMember('RVT-FUTURE', 'A', 4);

    foreach ([$legacy, $future] as $member) {
        expect(fn () => app(RevertCurrentRateBooking::class)($member->fresh(), rvtSuperAdmin(), 'Nothing to revert'))->toThrow(ValidationException::class);
    }

    expect($legacy->emiSchedule->fresh()->rate_booking_method)->toBe('current_rate');
});

test('the revert route is Super-Admin-only, needs a reason, and the member detail page reports whether a revert is possible', function () {
    $member = bcrMember('RVT-HTTP', 'A', 4);
    bcrBook($member);
    $url = "/super-admin/members/{$member->id}/revert-current-rate";

    // A member cannot revert their own booking.
    $this->actingAs($member->user)->post($url, ['reason' => 'Undo please'])->assertForbidden();
    expect($member->emiSchedule->fresh()->rate_booking_method)->toBe('current_rate');

    $this->actingAs(rvtSuperAdmin())->get("/super-admin/members/{$member->id}")
        ->assertInertia(fn ($page) => $page
            ->where('emi.rate_booking.method', 'current_rate')
            ->where('emi.rate_booking.can_revert', true)
            ->where('emi.rate_booking.revert_blocker', null)
            ->has('emi.rate_booking.events', 1));

    $this->actingAs(rvtSuperAdmin())->post($url, ['reason' => ''])->assertSessionHasErrors('reason');
    expect($member->emiSchedule->fresh()->rate_booking_method)->toBe('current_rate');

    $this->actingAs(rvtSuperAdmin())->post($url, ['reason' => 'Member phoned us'])
        ->assertRedirect("/super-admin/members/{$member->id}");

    expect($member->emiSchedule->fresh()->rate_booking_method)->toBe('future_rate');

    $this->actingAs(rvtSuperAdmin())->get("/super-admin/members/{$member->id}")
        ->assertInertia(fn ($page) => $page
            ->where('emi.rate_booking.method', 'future_rate')
            ->where('emi.rate_booking.can_revert', false)
            ->has('emi.rate_booking.events', 2));
});

test('the member detail page explains why a revert is not available once an EMI was paid after booking', function () {
    $member = bcrMember('RVT-BLOCKED', 'A', 4);
    bcrBook($member);
    $member->emiSchedule->installments()->where('installment_no', 5)->update(['status' => 'paid']);

    $this->actingAs(rvtSuperAdmin())->get("/super-admin/members/{$member->id}")
        ->assertInertia(fn ($page) => $page
            ->where('emi.rate_booking.can_revert', false)
            ->where('emi.rate_booking.revert_blocker', 'An EMI has been paid since the booking, so it can no longer be reverted.'));
});

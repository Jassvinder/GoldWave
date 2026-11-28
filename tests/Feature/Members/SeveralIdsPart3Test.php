<?php

use App\Actions\Members\ApplyMemberRestriction;
use App\Actions\Members\LiftMemberRestriction;
use App\Actions\Registration\PurgeUnpaidRegistrations;
use App\Actions\Registration\RegisterMemberIds;
use App\Models\EmiInstallment;
use App\Models\Member;
use App\Models\MemberRestriction;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\PaymentBatch;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * T-251 part 3 (DOMAIN_LOGIC.md §3.4, Docs/TEST.md scenarios 52–53) — "Pay for all my IDs", restrictions for the whole
 * person, and the unpaid purge by request.
 */
function p3Plan(): MembershipPlan
{
    return MembershipPlan::firstOrCreate(['code' => 'I'], [
        'name' => 'Gold Rise Lite', 'amount' => 2500, 'installment_count' => 20,
        'product_category' => 'gold', 'fixed_weight_grams' => 5, 'is_active' => true,
    ]);
}

function p3SuperAdmin(): User
{
    return User::where('role', 'super_admin')->firstOrFail();
}

/** @return Collection<int, Member> */
function p3Request(int $count, string $email = 'p3@example.test', string $mobile = '9876544001')
{
    return app(RegisterMemberIds::class)->forNewPerson([
        'sponsor_code' => 'P3-S',
        'placement_side' => 'left',
        'gender' => 'male',
        'name' => 'Part Three',
        'email' => $email,
        'mobile' => $mobile,
        'membership_plan_id' => p3Plan()->id,
        'payment_mode' => 'cash',
        'id_count' => $count,
    ]);
}

/** @return Collection<int, Member> three active IDs on ₹2,500 × 20, EMI 1 paid on each */
function p3ActivePerson()
{
    $ids = p3Request(3);
    test()->actingAs(p3SuperAdmin())
        ->post('/super-admin/cash-payments/'.$ids[0]->payments()->value('id').'/approve')
        ->assertRedirect();

    return Member::whereIn('id', $ids->pluck('id'))->orderBy('id')->get();
}

beforeEach(function () {
    $this->seed();
    Notification::fake();
    Member::create([
        'user_id' => User::factory()->create(['role' => 'member'])->id,
        'customer_id' => 'P3-S',
        'status' => 'active',
        'activated_at' => now(),
    ]);
});

test('Pay for all my IDs: one combined payment of each ID\'s next EMI, approved as one (scenario 52)', function () {
    $ids = p3ActivePerson();
    $user = $ids[0]->user;

    // ID B paid one more in advance, so its next EMI is #3 while A and C are on #2.
    $this->actingAs($user)->withSession(['current_member_id' => $ids[1]->id])
        ->post('/member/emi/'.EmiInstallment::where('emi_schedule_id', $ids[1]->emiSchedule->id)->where('installment_no', 2)->value('id').'/pay', ['mode' => 'cash'])
        ->assertSessionHasNoErrors();
    $this->actingAs(p3SuperAdmin())->post('/super-admin/cash-payments/'.Payment::where('member_id', $ids[1]->id)->where('type', 'emi_installment')->value('id').'/approve');

    $this->actingAs($user)->get('/member/emi')
        ->assertInertia(fn ($page) => $page
            ->where('all_ids.total', '7500')
            ->has('all_ids.items', 3)
            ->where('all_ids.items.1.installment_no', 3));

    $this->actingAs($user)->post('/member/emi/pay-all-ids', ['mode' => 'cash'])->assertSessionHasNoErrors();

    $batch = PaymentBatch::where('type', 'emi_installment')->sole();
    expect((float) $batch->amount)->toBe(7500.0)
        ->and($batch->payments)->toHaveCount(3);

    // While it waits, a single EMI of one of those IDs cannot be paid on its own, and the card shows it waiting.
    $this->actingAs($user->fresh())->withSession(['current_member_id' => $ids[0]->id])
        ->post('/member/emi/'.EmiInstallment::where('emi_schedule_id', $ids[0]->emiSchedule->id)->where('installment_no', 2)->value('id').'/pay', ['mode' => 'cash'])
        ->assertSessionHasErrors('installment');
    $this->actingAs($user)->get('/member/emi')
        ->assertInertia(fn ($page) => $page->where('all_ids.waiting.count', 3)->has('all_ids.items', 0));

    $this->actingAs(p3SuperAdmin())->post('/super-admin/cash-payments/'.$batch->payments->last()->id.'/approve')->assertRedirect();

    expect(EmiInstallment::where('emi_schedule_id', $ids[0]->emiSchedule->id)->where('status', 'paid')->count())->toBe(2)
        ->and(EmiInstallment::where('emi_schedule_id', $ids[1]->emiSchedule->id)->where('status', 'paid')->count())->toBe(3)
        ->and(EmiInstallment::where('emi_schedule_id', $ids[2]->emiSchedule->id)->where('status', 'paid')->count())->toBe(2);
});

test('a rejected combined EMI payment pays nothing, and the EMIs can be paid again; Online and fewer than 2 are refused', function () {
    $ids = p3ActivePerson();
    $user = $ids[0]->user;

    $this->actingAs($user)->post('/member/emi/pay-all-ids', ['mode' => 'cash'])->assertSessionHasNoErrors();
    $batch = PaymentBatch::where('type', 'emi_installment')->sole();
    $this->actingAs(p3SuperAdmin())->post("/super-admin/cash-payments/{$batch->payments->first()->id}/reject")->assertRedirect();

    expect(EmiInstallment::whereIn('emi_schedule_id', $ids->map(fn ($id) => $id->emiSchedule->id))->where('status', 'paid')->count())->toBe(3);

    $this->actingAs($user)->post('/member/emi/pay-all-ids', ['mode' => 'cash'])->assertSessionHasNoErrors();
    expect(PaymentBatch::where('type', 'emi_installment')->count())->toBe(2);

    // Everything is waiting now: nothing left to put in another one.
    $this->actingAs($user)->post('/member/emi/pay-all-ids', ['mode' => 'cash'])->assertSessionHasErrors('combined_emi');
    $this->actingAs($user)->post('/member/emi/pay-all-ids', ['mode' => 'online'])->assertSessionHasErrors();

    // A person with one ID sees no card.
    $single = Member::where('customer_id', 'P3-S')->sole();
    $this->actingAs($single->user)->get('/member/emi')->assertInertia(fn ($page) => $page->where('all_ids', null));
});

test('Account Block and Payout Hold cover every ID of the person, and are lifted from all (scenario 53)', function () {
    $ids = p3ActivePerson();
    $operator = p3SuperAdmin();

    app(ApplyMemberRestriction::class)($ids[2], MemberRestriction::ACCOUNT_BLOCK, 'Fraud check', $operator);

    foreach ($ids->fresh() as $id) {
        expect($id->blocked_at)->not->toBeNull();
    }
    expect(MemberRestriction::where('type', MemberRestriction::ACCOUNT_BLOCK)->whereNull('ended_at')->count())->toBe(3);

    // No login by any Customer ID.
    auth()->logout();
    $this->post('/member/login/password', ['customer_id' => $ids[0]->customer_id, 'password' => $ids[0]->customer_id])
        ->assertSessionHasErrors();
    $this->assertGuest();

    app(ApplyMemberRestriction::class)($ids[0], MemberRestriction::PAYOUT_HOLD, 'Bank check', $operator);
    app(LiftMemberRestriction::class)($ids[1], MemberRestriction::ACCOUNT_BLOCK, 'Cleared', $operator, alsoLiftPayoutHold: true);

    foreach ($ids->fresh() as $id) {
        expect($id->blocked_at)->toBeNull()->and($id->payout_hold_at)->toBeNull();
    }
    expect(MemberRestriction::whereNull('ended_at')->count())->toBe(0);

    // Someone else is never touched.
    expect(Member::where('customer_id', 'P3-S')->sole()->blocked_at)->toBeNull();
});

test('unpaid IDs of one request are deleted together, or kept together when an outsider is below one of them', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 1, 10));

    $ids = p3Request(3);
    $userId = $ids[0]->user_id;

    // An outsider's joining walks down the sponsor's Left: ID 1 → ID 2 → under ID 2.
    $outsider = p3Request(1, 'outsider@example.test', '9876544002')->sole();
    expect($outsider->placement_parent_id)->toBe($ids[1]->id);

    Carbon::setTestNow(Carbon::create(2026, 11, 2));

    // Only the outsider goes; the 3 stay together (none deleted from the bottom up).
    expect(app(PurgeUnpaidRegistrations::class)())->toBe(1);
    expect(Member::whereIn('id', $ids->pluck('id'))->count())->toBe(3);

    // Next run: nobody else below, so all 3, their batch and the user go together.
    expect(app(PurgeUnpaidRegistrations::class)())->toBe(3);
    expect(Member::whereIn('id', $ids->pluck('id'))->count())->toBe(0)
        ->and(PaymentBatch::count())->toBe(0)
        ->and(User::find($userId))->toBeNull();

    Carbon::setTestNow();
});

test('an unpaid "Add ID" is deleted without touching the person\'s other ID or login', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 1, 10));

    $first = Member::where('customer_id', 'P3-S')->sole();
    $added = app(RegisterMemberIds::class)->forExistingPerson($first->user, [
        'id_count' => 1,
        'membership_plan_id' => p3Plan()->id,
        'parent_code' => 'P3-S',
        'placement_side' => 'right',
        'payment_mode' => 'cash',
    ], p3SuperAdmin())->sole();

    Carbon::setTestNow(Carbon::create(2026, 11, 2));

    expect(app(PurgeUnpaidRegistrations::class)())->toBe(1);
    expect(Member::find($added->id))->toBeNull()
        ->and($first->fresh()->user_id)->toBe($first->user_id)
        ->and(User::find($first->user_id))->not->toBeNull();

    Carbon::setTestNow();
});

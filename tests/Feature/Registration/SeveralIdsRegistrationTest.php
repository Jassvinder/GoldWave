<?php

use App\Actions\Payments\ApproveCashPayment;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\PaymentBatch;
use App\Models\User;
use App\Notifications\CashPaymentAwaitingApproval;
use App\Notifications\CashPaymentDecided;
use App\Support\MemberIds;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * T-251 part 2 (DOMAIN_LOGIC.md §3.4, Docs/TEST.md scenario 50) — several IDs on one registration, one combined
 * payment, approved or rejected as one.
 */
function sirSponsor(): Member
{
    return Member::create([
        'user_id' => User::factory()->create(['role' => 'member'])->id,
        'customer_id' => 'SIR-S',
        'status' => 'active',
        'activated_at' => now(),
    ]);
}

function sirPlan(): MembershipPlan
{
    return MembershipPlan::firstOrCreate(['code' => 'I'], [
        'name' => 'Gold Rise Lite', 'amount' => 2500, 'installment_count' => 20,
        'product_category' => 'gold', 'fixed_weight_grams' => 5, 'is_active' => true,
    ]);
}

/** @return array<string, mixed> */
function sirPayload(array $overrides = []): array
{
    return array_merge([
        'sponsor_code' => 'SIR-S',
        'placement_side' => 'left',
        'gender' => 'female',
        'name' => 'Several Ids',
        'email' => 'several@example.test',
        'mobile' => '9876522001',
        'membership_plan_id' => sirPlan()->id,
        'payment_mode' => 'upi',
        'upi_reference' => 'UTRSEVERAL11',
        'upi_screenshot' => UploadedFile::fake()->image('paid.png'),
        'id_count' => 11,
    ], $overrides);
}

beforeEach(function () {
    $this->seed();
    Storage::fake('local');
    sirSponsor();
});

test('11 IDs on one registration: one person, sponsor and places as designed, one combined payment (scenario 50)', function () {
    Notification::fake();

    $this->post('/join', sirPayload())->assertSessionHasNoErrors()->assertRedirect();

    $user = User::where('email', 'several@example.test')->sole();
    $ids = Member::where('user_id', $user->id)->orderBy('id')->get();
    $sponsor = Member::where('customer_id', 'SIR-S')->sole();

    expect($ids)->toHaveCount(11)
        ->and($ids->pluck('customer_id')->filter())->toBeEmpty()
        ->and($ids->pluck('status')->unique()->all())->toBe(['payment_pending'])
        ->and($ids->pluck('gender')->unique()->all())->toBe(['female']);

    // ID 1: the form's sponsor and side. IDs 2–11: sponsored by ID 1, level by level under it.
    expect($ids[0]->sponsor_id)->toBe($sponsor->id)
        ->and($ids[0]->placement_parent_id)->toBe($sponsor->id)
        ->and($ids[0]->placement_side)->toBe('left');

    $expected = [1 => [0, 'left'], 2 => [0, 'right'], 3 => [1, 'left'], 4 => [1, 'right'], 5 => [2, 'left'],
        6 => [2, 'right'], 7 => [3, 'left'], 8 => [3, 'right'], 9 => [4, 'left'], 10 => [4, 'right']];

    foreach ($expected as $index => [$parentIndex, $side]) {
        expect($ids[$index]->sponsor_id)->toBe($ids[0]->id)
            ->and($ids[$index]->placement_parent_id)->toBe($ids[$parentIndex]->id)
            ->and($ids[$index]->placement_side)->toBe($side);
    }

    $batch = PaymentBatch::sole();
    expect((float) $batch->amount)->toBe(27500.0)
        ->and($batch->upi_reference)->toBe('UTRSEVERAL11')
        ->and($batch->upi_screenshot_path)->not->toBeNull()
        ->and($batch->payments)->toHaveCount(11)
        ->and($batch->payments->pluck('amount')->map(fn ($a) => (float) $a)->unique()->all())->toBe([2500.0])
        ->and($batch->payments->pluck('upi_reference')->filter())->toBeEmpty();

    // One bell alert for the whole request.
    Notification::assertSentTimes(CashPaymentAwaitingApproval::class, 1);

    // Payment Approvals shows it as ONE row with the total.
    $this->actingAs(User::factory()->create(['role' => 'super_admin']))
        ->get('/super-admin/cash-payments')
        ->assertInertia(fn ($page) => $page
            ->has('pending', 1)
            ->where('pending.0.amount', '27500.00')
            ->where('pending.0.upi_reference', 'UTRSEVERAL11')
            ->where('pending.0.combined.count', 11)
            ->where('pending.0.combined.each', '2500.00'));
});

test('approving the combined payment activates all 11 IDs in order; the password is ID 1\'s Customer ID', function () {
    $this->post('/join', sirPayload())->assertRedirect();

    $batch = PaymentBatch::sole();
    $anyRow = $batch->payments->last();

    // Any row of it approves the whole combined payment.
    $this->actingAs(User::factory()->create(['role' => 'super_admin']))
        ->post("/super-admin/cash-payments/{$anyRow->id}/approve")->assertRedirect();

    $ids = Member::where('user_id', User::where('email', 'several@example.test')->value('id'))->orderBy('id')->get();
    $customerIds = $ids->pluck('customer_id');

    expect($batch->fresh()->cash_status)->toBe('approved')
        ->and($ids->pluck('status')->unique()->all())->toBe(['active'])
        ->and($customerIds->filter())->toHaveCount(11)
        ->and($customerIds->all())->toBe($customerIds->sort(SORT_NATURAL)->values()->all())
        ->and(Payment::where('payment_batch_id', $batch->id)->where('status', 'paid')->count())->toBe(11);

    foreach ($ids as $id) {
        expect($id->emiSchedule->installments()->count())->toBe(20)
            ->and($id->emiSchedule->installments()->where('status', 'paid')->count())->toBe(1);
    }

    $user = $ids[0]->user;
    expect(Hash::check($ids[0]->customer_id, $user->password))->toBeTrue();

    // Any of the 11 Customer IDs logs in with that one password, and opens that ID.
    auth()->logout();
    $this->post('/member/login/password', ['customer_id' => $ids[6]->customer_id, 'password' => $ids[0]->customer_id])
        ->assertRedirect();
    $this->assertAuthenticatedAs($user);
    expect(session(MemberIds::SESSION_KEY))->toBe($ids[6]->id);
});

test('rejecting the combined payment rejects every ID; none gets a Customer ID', function () {
    Notification::fake();
    $this->post('/join', sirPayload(['id_count' => 3, 'payment_mode' => 'cash']))->assertRedirect();

    $batch = PaymentBatch::sole();
    $this->actingAs(User::factory()->create(['role' => 'super_admin']))
        ->post("/super-admin/cash-payments/{$batch->payments->first()->id}/reject")->assertRedirect();

    expect($batch->fresh()->cash_status)->toBe('rejected')
        ->and(Payment::where('payment_batch_id', $batch->id)->pluck('cash_status')->unique()->all())->toBe(['rejected'])
        ->and(Member::whereIn('id', $batch->payments->pluck('member_id'))->whereNotNull('customer_id')->count())->toBe(0);

    Notification::assertSentTimes(CashPaymentDecided::class, 1);
});

test('one ID of a combined payment is never approved or rejected on its own', function () {
    $this->post('/join', sirPayload(['id_count' => 2, 'payment_mode' => 'cash']))->assertRedirect();
    $row = PaymentBatch::sole()->payments->first();

    expect(fn () => app(ApproveCashPayment::class)($row, User::factory()->create(['role' => 'super_admin'])))
        ->toThrow(ValidationException::class);

    expect($row->fresh()->status)->toBe('pending');
});

test('the limit, the payment mode and the Ref ID are checked', function () {
    $this->post('/join', sirPayload(['id_count' => 12]))->assertSessionHasErrors(['id_count' => 'At most 11 IDs per person.']);
    $this->post('/join', sirPayload(['id_count' => 0]))->assertSessionHasErrors('id_count');

    // A Ref ID used by a combined payment cannot be used again, alone or combined.
    $this->post('/join', sirPayload(['id_count' => 2]))->assertSessionHasNoErrors();
    $this->post('/join', sirPayload(['id_count' => 1, 'email' => 'x@example.test', 'mobile' => '9876522002']))
        ->assertSessionHasErrors('upi_reference');

    expect(Member::whereNotNull('user_id')->where('customer_id', null)->count())->toBe(2);
});

/** A person P with two active IDs (first, second), with KYC and a bank detail on the first. */
function sirPersonWithTwoIds(): array
{
    $user = User::factory()->create(['role' => 'member', 'mobile' => '9876522100', 'password' => 'p-pass']);
    $first = Member::create([
        'user_id' => $user->id, 'customer_id' => 'SIR-P1', 'status' => 'active', 'activated_at' => now(),
        'gender' => 'male', 'pan_card' => 'ABCDE1234F', 'address' => 'Jaipur',
    ]);
    $second = Member::create([
        'user_id' => $user->id, 'customer_id' => 'SIR-P2', 'status' => 'active', 'activated_at' => now(), 'sponsor_id' => $first->id,
    ]);
    $first->bankDetails()->create([
        'account_holder_name' => 'P', 'account_number' => '123456789', 'ifsc_code' => 'SBIN0000001', 'bank_name' => 'SBI',
    ]);

    return [$user, $first, $second];
}

test('Add ID: placed anywhere, sponsored by the first ID, KYC and bank copied, password kept (scenario 51)', function () {
    [$user, $first] = sirPersonWithTwoIds();
    $q = Member::create(['user_id' => User::factory()->create(['role' => 'member'])->id, 'customer_id' => 'SIR-Q', 'status' => 'active', 'activated_at' => now()]);
    $direct = MembershipPlan::where('code', 'E')->sole();

    $this->actingAs($user)->withSession([MemberIds::SESSION_KEY => Member::where('customer_id', 'SIR-P2')->value('id')])
        ->get(route('member.my-ids.index'))
        ->assertInertia(fn ($page) => $page->component('member/my-ids')->has('ids', 2)->where('add_form.can_add', 9));

    $this->post(route('member.my-ids.store'), [
        'id_count' => 1, 'membership_plan_id' => $direct->id, 'parent_code' => 'SIR-Q',
        'placement_side' => 'right', 'payment_mode' => 'cash',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $new = Member::where('user_id', $user->id)->orderByDesc('id')->first();
    expect($new->placement_parent_id)->toBe($q->id)
        ->and($new->placement_side)->toBe('right')
        ->and($new->sponsor_id)->toBe($first->id)
        ->and($new->pan_card)->toBe('ABCDE1234F')
        ->and($new->address)->toBe('Jaipur')
        ->and($new->bankDetails()->value('account_number'))->toBe('123456789')
        ->and(PaymentBatch::count())->toBe(0)
        ->and((float) $new->payments()->value('amount'))->toBe((float) $direct->amount);

    $payment = $new->payments()->sole();
    $this->actingAs(User::factory()->create(['role' => 'super_admin']))
        ->post("/super-admin/cash-payments/{$payment->id}/approve")->assertRedirect();

    expect($new->fresh()->status)->toBe('active')
        ->and(Hash::check('p-pass', $user->fresh()->password))->toBeTrue();
});

test('Add ID: several at once fill under the first new one; the limit counts pending IDs; strangers cannot add', function () {
    [$user, $first] = sirPersonWithTwoIds();

    $this->actingAs($user)->post(route('member.my-ids.store'), [
        'id_count' => 3, 'membership_plan_id' => sirPlan()->id, 'parent_code' => 'SIR-P1',
        'placement_side' => 'left', 'payment_mode' => 'upi', 'upi_reference' => 'UTRADDIDS3',
        'upi_screenshot' => UploadedFile::fake()->image('paid.png'),
    ])->assertSessionHasNoErrors();

    $new = Member::where('user_id', $user->id)->orderBy('id')->get()->slice(2)->values();
    expect($new)->toHaveCount(3)
        ->and($new[0]->placement_parent_id)->toBe($first->id)
        ->and($new[1]->placement_parent_id)->toBe($new[0]->id)
        ->and($new[1]->placement_side)->toBe('left')
        ->and($new[2]->placement_parent_id)->toBe($new[0]->id)
        ->and($new[2]->placement_side)->toBe('right')
        ->and($new->pluck('sponsor_id')->unique()->all())->toBe([$first->id])
        ->and((float) PaymentBatch::sole()->amount)->toBe(7500.0)
        ->and(PaymentBatch::sole()->upi_reference)->toBe('UTRADDIDS3');

    // 5 held (2 active + 3 pending); 7 more would pass 11.
    $this->post(route('member.my-ids.store'), [
        'id_count' => 7, 'membership_plan_id' => sirPlan()->id, 'parent_code' => 'SIR-P1',
        'placement_side' => 'left', 'payment_mode' => 'cash',
    ])->assertSessionHasErrors(['id_count' => 'At most 11 IDs per person. This person can add 6 more.']);

    // Wallet is not offered for Add ID.
    $this->post(route('member.my-ids.store'), [
        'id_count' => 1, 'membership_plan_id' => sirPlan()->id, 'parent_code' => 'SIR-P1',
        'placement_side' => 'left', 'payment_mode' => 'wallet',
    ])->assertSessionHasErrors('payment_mode');

    // An unknown parent is refused.
    $this->post(route('member.my-ids.store'), [
        'id_count' => 1, 'membership_plan_id' => sirPlan()->id, 'parent_code' => 'NOPE',
        'placement_side' => 'left', 'payment_mode' => 'cash',
    ])->assertSessionHasErrors('parent_code');

    // Another member's request only ever adds to their own person; there is no field naming someone else.
    $stranger = Member::create(['user_id' => User::factory()->create(['role' => 'member'])->id, 'customer_id' => 'SIR-R', 'status' => 'active', 'activated_at' => now()]);
    $this->actingAs($stranger->user)->post(route('member.my-ids.store'), [
        'id_count' => 1, 'membership_plan_id' => sirPlan()->id, 'parent_code' => 'SIR-P1',
        'placement_side' => 'right', 'payment_mode' => 'cash',
    ])->assertSessionHasNoErrors();

    expect(Member::where('user_id', $user->id)->count())->toBe(5)
        ->and(Member::where('user_id', $stranger->user_id)->count())->toBe(2);
});

test('Add ID from Member Detail: Super Admin and the company Admin can, a member cannot', function () {
    [$user, $first] = sirPersonWithTwoIds();
    $payload = ['id_count' => 1, 'membership_plan_id' => sirPlan()->id, 'parent_code' => 'SIR-P1', 'placement_side' => 'right', 'payment_mode' => 'cash'];

    $this->actingAs(User::factory()->create(['role' => 'super_admin']))
        ->get(route('super-admin.members.show', $first))
        ->assertInertia(fn ($page) => $page->has('person_ids.ids', 2)->where('person_ids.add_form.held', 2));

    $this->post(route('super-admin.members.add-ids', $first), $payload)->assertSessionHasNoErrors();
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->post(route('super-admin.members.add-ids', $first), $payload)->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('super-admin.members.add-ids', $first), $payload)->assertForbidden();

    expect(Member::where('user_id', $user->id)->count())->toBe(4)
        ->and(PaymentBatch::count())->toBe(0);
});

test('a one-ID registration is unchanged: no combined payment', function () {
    $this->post('/join', sirPayload(['id_count' => 1]))->assertSessionHasNoErrors()->assertRedirect();

    $payment = Payment::where('upi_reference', 'UTRSEVERAL11')->sole();
    expect(PaymentBatch::count())->toBe(0)
        ->and($payment->payment_batch_id)->toBeNull();
});

test('Complete Profile once fills every ID of the person that has not filled it; one bank verification covers them', function () {
    Storage::fake('public');
    $this->post('/join', sirPayload(['id_count' => 3]))->assertRedirect();
    $this->actingAs($admin = User::factory()->create(['role' => 'super_admin']))
        ->post('/super-admin/cash-payments/'.PaymentBatch::sole()->payments->first()->id.'/approve');

    $user = User::where('email', 'several@example.test')->sole();
    $ids = Member::where('user_id', $user->id)->orderBy('id')->get();

    // A stranger with no profile yet is never touched.
    $stranger = Member::where('customer_id', 'SIR-S')->sole();

    $this->actingAs($user)->withSession([MemberIds::SESSION_KEY => $ids[1]->id])
        ->post('/member/pending-profile', [
            'pan_card' => 'ABCDE1234F',
            'aadhaar_card' => '123456789012',
            'profile_photo' => UploadedFile::fake()->image('photo.jpg'),
            'address' => '12 Gold Street',
            'bank_account_holder_name' => 'Several Ids',
            'bank_account_number' => '1234567890',
            'bank_ifsc_code' => 'TEST0001234',
            'bank_name' => 'Test Bank',
            'bank_proof_document' => UploadedFile::fake()->image('cheque.jpg'),
        ])->assertSessionHasNoErrors()->assertRedirect('/member/profile');

    foreach ($ids->fresh() as $id) {
        expect($id->pending_fields_submitted_at)->not->toBeNull()
            ->and($id->pan_card)->toBe('ABCDE1234F')
            ->and($id->address)->toBe('12 Gold Street')
            ->and($id->bankDetails()->count())->toBe(1);
    }

    expect($stranger->fresh()->pending_fields_submitted_at)->toBeNull();

    // Verifying the bank details on one ID verifies the same account on the others.
    $this->actingAs($admin)->post("/super-admin/members/{$ids[2]->id}/verify-bank-detail")->assertRedirect();

    foreach ($ids as $id) {
        expect($id->bankDetails()->sole()->verified_at)->not->toBeNull();
    }
});

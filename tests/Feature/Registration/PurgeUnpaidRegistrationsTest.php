<?php

use App\Actions\Registration\PurgeUnpaidRegistrations;
use App\Models\EmiSchedule;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * T-243 (DOMAIN_LOGIC.md §2.2a) — a registration still unpaid one calendar month later is deleted completely,
 * unless a GPay/UPI payment with a Ref ID waits for approval or someone is placed/sponsored under it.
 */
function purgeSponsor(): Member
{
    return Member::create([
        'user_id' => User::factory()->create(['role' => 'member'])->id,
        'customer_id' => 'GWL700',
        'status' => 'active',
        'activated_at' => now(),
    ]);
}

function purgeJoin(string $email, string $mobile, string $side = 'left'): Member
{
    test()->post('/join', [
        'sponsor_code' => 'GWL700',
        'placement_side' => $side,
        'gender' => 'male',
        'name' => 'Unpaid Member',
        'email' => $email,
        'mobile' => $mobile,
        'membership_plan_id' => MembershipPlan::where('code', 'A')->value('id'),
        'payment_mode' => 'cash',
    ])->assertRedirect();

    return Member::whereHas('user', fn ($q) => $q->where('email', $email))->firstOrFail();
}

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 1, 10));
    $this->seed();
    purgeSponsor();
});

afterEach(function () {
    Carbon::setTestNow();
});

test('an unpaid cash registration is deleted one month later, not a day before', function () {
    $member = purgeJoin('unpaid@example.test', '9876520001');
    $userId = $member->user_id;
    expect($member->customer_id)->toBeNull()->and($member->status)->toBe('payment_pending');

    Carbon::setTestNow(Carbon::create(2026, 10, 31, 23)); // still under one month
    expect(app(PurgeUnpaidRegistrations::class)())->toBe(0);
    expect(Member::find($member->id))->not->toBeNull();

    Carbon::setTestNow(Carbon::create(2026, 11, 1, 10)); // one month after
    expect(app(PurgeUnpaidRegistrations::class)())->toBe(1);

    expect(Member::find($member->id))->toBeNull()
        ->and(User::find($userId))->toBeNull()
        ->and(Payment::where('member_id', $member->id)->count())->toBe(0)
        ->and(EmiSchedule::where('member_id', $member->id)->count())->toBe(0);

    // The freed left slot under the sponsor is used by the next registration.
    $next = purgeJoin('next@example.test', '9876520002');
    expect($next->placement_parent_id)->toBe(Member::where('customer_id', 'GWL700')->value('id'))
        ->and($next->placement_side)->toBe('left');
});

test('a paid (active) member, a GPay/UPI claim waiting for approval and a member with someone below are kept', function () {
    Storage::fake('local');
    $sponsorId = Member::where('customer_id', 'GWL700')->value('id');

    $paid = purgeJoin('paid@example.test', '9876520003', 'left');
    $superAdmin = User::where('role', 'super_admin')->firstOrFail();
    $this->actingAs($superAdmin)->post('/super-admin/cash-payments/'.$paid->payments()->value('id').'/approve');
    expect($paid->fresh()->status)->toBe('active');

    $upi = purgeJoin('upi@example.test', '9876520004', 'right');
    Storage::disk('local')->put('payment-proofs/proof.webp', 'x');
    $upi->payments()->update(['mode' => 'upi', 'upi_reference' => 'UPI123456', 'upi_screenshot_path' => 'payment-proofs/proof.webp']);

    // A pending member with someone placed under them (the resolver walks down through pending members too).
    $withChild = purgeJoin('parent@example.test', '9876520005', 'left');
    expect($withChild->placement_parent_id)->toBe($paid->id);
    $child = purgeJoin('child@example.test', '9876520006', 'left');
    expect($child->placement_parent_id)->toBe($withChild->id);

    Carbon::setTestNow(Carbon::create(2026, 11, 2));

    // Only the child (unpaid, nobody below) is deleted; the next run then also deletes its now-childless parent.
    expect(app(PurgeUnpaidRegistrations::class)())->toBe(1);
    expect(Member::find($child->id))->toBeNull()
        ->and(Member::find($paid->id))->not->toBeNull()
        ->and(Member::find($upi->id))->not->toBeNull()
        ->and(Storage::disk('local')->exists('payment-proofs/proof.webp'))->toBeTrue()
        ->and(PurgeUnpaidRegistrations::keepReason($upi))->toBe('gpay_upi_waiting_for_approval');

    expect(app(PurgeUnpaidRegistrations::class)())->toBe(1);
    expect(Member::find($withChild->id))->toBeNull()
        ->and(Member::find($sponsorId))->not->toBeNull();

    // A rejected GPay/UPI claim no longer protects it: deleted with its proof file.
    $upi->payments()->update(['status' => 'failed', 'cash_status' => 'rejected']);
    expect(app(PurgeUnpaidRegistrations::class)())->toBe(1);
    expect(Member::find($upi->id))->toBeNull()
        ->and(Storage::disk('local')->exists('payment-proofs/proof.webp'))->toBeFalse();
});

<?php

use App\Actions\Compensation\CalculateLevelIncome;
use App\Actions\Payments\ApproveCashPayment;
use App\Actions\Payments\InitiateEmiInstallmentPayment;
use App\Actions\Store\AllocateStoreInventoryItem;
use App\Actions\Store\CreateStore;
use App\Actions\Store\DecideStoreEmiBooking;
use App\Actions\Store\RequestStoreEmiBooking;
use App\Jobs\ProcessEmiDueStatuses;
use App\Models\EmiInstallment;
use App\Models\IncomeLedgerCalculation;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\MetalRate;
use App\Models\Payment;
use App\Models\StoreEmiBooking;
use App\Models\StoreInventoryItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * T-185b (30-09-2026) — Docs/TEST.md scenario 41: a Repurchase on EMI breaks by itself at 3 overdue EMIs; the unpaid EMIs
 * are cancelled, the piece returns to stock, and the member is owed silver for the principal paid at the silver rate of
 * the last paid EMI's date.
 */
function sebkOperator(): User
{
    return User::where('role', 'super_admin')->firstOrFail();
}

function sebkMember(string $customerId, ?Member $sponsor = null): Member
{
    $member = Member::create([
        'user_id' => User::factory()->create(['role' => 'member'])->id,
        'customer_id' => $customerId,
        'sponsor_id' => $sponsor?->id,
        'membership_plan_id' => MembershipPlan::where('code', 'E')->value('id'),
        'status' => 'active',
        'activated_at' => now(),
    ]);
    Payment::create(['member_id' => $member->id, 'type' => 'registration', 'amount' => 20000, 'mode' => 'cash', 'status' => 'paid', 'paid_at' => now()]);

    return $member;
}

/** @return array{booking: StoreEmiBooking, anklet: StoreInventoryItem} */
function sebkActiveBooking(Member $member, string $prefix): array
{
    $admin = User::factory()->create(['role' => 'store_admin']);
    $store = app(CreateStore::class)("{$prefix} Store", $admin, '9998887777', 'Test City', 1000000, 0, sebkOperator());
    $anklet = app(AllocateStoreInventoryItem::class)($store, 'Silver Anklet', 'silver', 100, 2, 35000, sebkOperator());
    $booking = app(RequestStoreEmiBooking::class)($store, $admin, $member, $anklet, 10);
    app(DecideStoreEmiBooking::class)->approve($booking, sebkOperator());

    return ['booking' => $booking->fresh(), 'anklet' => $anklet];
}

function sebkPay(Member $member, StoreEmiBooking $booking, int $no): void
{
    $installment = EmiInstallment::where('emi_schedule_id', $booking->emi_schedule_id)->where('installment_no', $no)->firstOrFail();
    $installment->update(['status' => 'due']);
    app(ApproveCashPayment::class)(app(InitiateEmiInstallmentPayment::class)($member->fresh(), $installment, 'cash'), sebkOperator());
}

function sebkOverdue(StoreEmiBooking $booking, array $nos): void
{
    EmiInstallment::where('emi_schedule_id', $booking->emi_schedule_id)->whereIn('installment_no', $nos)->update(['status' => 'overdue']);
}

beforeEach(function () {
    $this->seed(); // silver ₹350/g
    DB::table('rule_values')->where('key', 'level_income_min_directs')->update(['value' => json_encode([])]);
});

test('TEST.md scenario 41: after 2 paid EMIs and 3 overdue, the booking breaks — 17.500 g of silver owed for ₹7,000 at ₹400/g', function () {
    $member = sebkMember('SEBK-M');
    ['booking' => $booking, 'anklet' => $anklet] = sebkActiveBooking($member, 'SEBK');
    expect($anklet->fresh()->quantity)->toBe(1);

    sebkPay($member, $booking, 1); // ₹3,850
    MetalRate::create(['metal' => 'silver', 'rate_per_gram' => 400, 'effective_from' => now()->toDateString(), 'created_by' => sebkOperator()->id]);
    sebkPay($member, $booking, 2); // ₹3,815, paid on a ₹400/g day
    $levelBefore = IncomeLedgerCalculation::where('type', 'level_income')->count();

    sebkOverdue($booking, [3, 4]);
    (new ProcessEmiDueStatuses)->handle();
    expect($booking->fresh()->status)->toBe('active'); // only 2 overdue

    sebkOverdue($booking, [5]);
    (new ProcessEmiDueStatuses)->handle();

    $booking = $booking->fresh();
    expect($booking->status)->toBe('broken');
    expect($booking->broken_at)->not->toBeNull();
    expect((float) $booking->principal_paid)->toBe(7000.0);
    expect((float) $booking->silver_rate_per_gram)->toBe(400.0);
    expect((float) $booking->silver_grams_owed)->toBe(17.5);
    expect(EmiInstallment::where('emi_schedule_id', $booking->emi_schedule_id)->where('status', 'cancelled')->pluck('installment_no')->sort()->values()->all())
        ->toBe(range(3, 10));
    expect($anklet->fresh()->quantity)->toBe(2);
    expect(IncomeLedgerCalculation::where('type', 'level_income')->count())->toBe($levelBefore);
    expect($member->fresh()->hasOverdueEmi())->toBeFalse();
    expect($member->fresh()->user->notifications()->where('data->key', 'store_emi_booking_broken')->count())->toBe(1);

    $this->actingAs($member->user)->get('/member/emi')->assertInertia(fn ($page) => $page
        ->where('store_emi.status', 'broken')
        ->where('store_emi.silver_grams_owed', '17.500'));

    // A cancelled EMI can never be paid again.
    $cancelled = EmiInstallment::where('emi_schedule_id', $booking->emi_schedule_id)->where('installment_no', 3)->firstOrFail();
    expect(fn () => app(InitiateEmiInstallmentPayment::class)($member->fresh(), $cancelled, 'cash'))->toThrow(ValidationException::class);
});

test('earnings held because of the overdue store EMIs are released when the booking breaks', function () {
    $member = sebkMember('SEBK-H');
    ['booking' => $booking] = sebkActiveBooking($member, 'SEBKH');
    sebkPay($member, $booking, 1);
    sebkOverdue($booking, [2, 3]);

    $downline = sebkMember('SEBK-H-P', $member);
    app(CalculateLevelIncome::class)(Payment::where('member_id', $downline->id)->firstOrFail());
    expect((float) $member->fresh()->wallet_balance)->toBe(0.0);

    sebkOverdue($booking, [4]);
    (new ProcessEmiDueStatuses)->handle();

    expect($booking->fresh()->status)->toBe('broken');
    expect((float) $member->fresh()->wallet_balance)->toBe(1000.0); // 5% of ₹20,000, released.
});

test('with nothing paid the booking breaks with nothing owed, and the member can book again', function () {
    $member = sebkMember('SEBK-Z');
    ['booking' => $booking, 'anklet' => $anklet] = sebkActiveBooking($member, 'SEBKZ');
    sebkOverdue($booking, [1, 2, 3]);

    (new ProcessEmiDueStatuses)->handle();

    $booking = $booking->fresh();
    expect($booking->status)->toBe('broken');
    expect((float) $booking->principal_paid)->toBe(0.0);
    expect((float) $booking->silver_grams_owed)->toBe(0.0);
    expect(StoreEmiBooking::where('member_id', $member->id)->open()->exists())->toBeFalse();
    expect($anklet->fresh()->quantity)->toBe(2);
});

test('the break threshold is the Super Admin setting', function () {
    DB::table('rule_values')->where('key', 'store_emi_break_overdue_count')->update(['value' => json_encode(2)]);
    $member = sebkMember('SEBK-T');
    ['booking' => $booking] = sebkActiveBooking($member, 'SEBKT');
    sebkOverdue($booking, [1, 2]);

    (new ProcessEmiDueStatuses)->handle();

    expect($booking->fresh()->status)->toBe('broken');
});

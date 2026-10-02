<?php

use App\Actions\Compensation\CalculateLevelIncome;
use App\Actions\Store\AllocateStoreInventoryItem;
use App\Actions\Store\CreateStore;
use App\Actions\Store\QuoteStoreEmiBooking;
use App\Models\EmiInstallment;
use App\Models\IncomeLedgerCalculation;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\PairEntry;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreEmiBooking;
use App\Models\StoreInventoryItem;
use App\Models\StoreProfitDistribution;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use Illuminate\Support\Facades\DB;

/**
 * T-185a (30-09-2026) — Docs/TEST.md scenario 40: a member's Repurchase on EMI. The Store Admin requests one piece over 10
 * or 20 Current Rate EMIs, Super Admin approves (rate locked, piece held), and the member pays from the EMI page like any
 * EMI — the upline gets Level Income only; no store income, no Pair.
 */
function sebSuperAdmin(): User
{
    return User::where('role', 'super_admin')->firstOrFail();
}

function sebMember(string $customerId, ?Member $sponsor = null): Member
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

/** @return array{admin: User, store: Store, anklet: StoreInventoryItem} */
function sebStore(string $prefix): array
{
    $admin = User::factory()->create(['role' => 'store_admin']);
    $store = app(CreateStore::class)("{$prefix} Store", $admin, '9998887777', 'Test City', 1000000, 0, sebSuperAdmin());
    $anklet = app(AllocateStoreInventoryItem::class)($store, 'Silver Anklet', 'silver', 100, 2, 35000, sebSuperAdmin());

    return ['admin' => $admin, 'store' => $store, 'anklet' => $anklet];
}

function sebRequest(User $admin, Member $member, StoreInventoryItem $item, int $count = 10): StoreEmiBooking
{
    test()->actingAs($admin)->post('/admin/store-emi', [
        'customer_id' => $member->customer_id,
        'inventory_item_id' => $item->id,
        'installment_count' => $count,
    ])->assertSessionHasNoErrors();

    return StoreEmiBooking::where('member_id', $member->id)->latest('id')->firstOrFail();
}

beforeEach(function () {
    $this->seed(); // silver ₹350/g, 0% making
    DB::table('rule_values')->where('key', 'level_income_min_directs')->update(['value' => json_encode([])]);
});

test('TEST.md scenario 40: request → approval locks ₹35,000 over 10 declining EMIs, holds the piece, and EMI #1 is due today', function () {
    ['admin' => $admin, 'anklet' => $anklet] = sebStore('SEB');
    $member = sebMember('SEB-M');

    $booking = sebRequest($admin, $member, $anklet);
    expect($booking->status)->toBe('pending');
    expect((float) $booking->estimate['total_value'])->toBe(35000.0);
    expect(sebSuperAdmin()->notifications()->where('data->key', 'store_emi_booking_requested')->count())->toBe(1);
    expect($anklet->fresh()->quantity)->toBe(2); // nothing held yet

    $this->actingAs(sebSuperAdmin())->post("/super-admin/store-emi-bookings/{$booking->id}/approve")->assertSessionHasNoErrors();

    $booking = $booking->fresh();
    expect($booking->status)->toBe('active');
    expect($anklet->fresh()->quantity)->toBe(1);

    $schedule = $booking->emiSchedule;
    expect($schedule->kind)->toBe('store_repurchase');
    expect($schedule->membership_plan_id)->toBeNull();
    expect((float) $schedule->rate_per_gram_at_booking)->toBe(350.0);
    $installments = $schedule->installments()->orderBy('installment_no')->get();
    expect($installments->pluck('amount')->map(fn ($a) => (float) $a)->all())
        ->toBe([3850.0, 3815.0, 3780.0, 3745.0, 3710.0, 3675.0, 3640.0, 3605.0, 3570.0, 3535.0]);
    expect(round((float) $installments->sum('amount'), 2))->toBe(36925.0);
    expect($installments[0]->status)->toBe('due');
    expect($installments[0]->due_date->toDateString())->toBe(now()->toDateString());
    expect($installments[1]->status)->toBe('upcoming');

    // The member's plan schedule relation never sees it.
    expect($member->fresh()->emiSchedule)->toBeNull();
    expect($member->fresh()->user->notifications()->where('data->key', 'store_emi_booking_decided')->count())->toBe(1);
});

test('TEST.md scenario 40: paying EMI #1 gives S1 ₹192.50 and S2 ₹77 of Level Income only, and paying all the rest (₹31,500) completes the booking', function () {
    ['admin' => $admin, 'anklet' => $anklet] = sebStore('SEBP');
    $s2 = sebMember('SEBP-S2');
    $s1 = sebMember('SEBP-S1', $s2);
    $member = sebMember('SEBP-M', $s1);
    $booking = sebRequest($admin, $member, $anklet);
    $this->actingAs(sebSuperAdmin())->post("/super-admin/store-emi-bookings/{$booking->id}/approve");
    $first = $booking->fresh()->emiSchedule->installments()->where('installment_no', 1)->firstOrFail();

    $this->actingAs($member->user)->post("/member/emi/{$first->id}/pay", ['mode' => 'cash'])->assertSessionHasNoErrors();
    $payment = Payment::find($first->fresh()->payment_id);
    $this->actingAs(sebSuperAdmin())->post("/super-admin/cash-payments/{$payment->id}/approve");

    expect($first->fresh()->status)->toBe('paid');
    $level = IncomeLedgerCalculation::where('source_payment_id', $payment->id)->where('type', 'level_income')->get()->keyBy('level_no');
    expect((float) $level[1]->amount)->toBe(192.5);
    expect((float) $level[2]->amount)->toBe(77.0);
    expect(IncomeLedgerCalculation::where('type', 'purchase_repurchase')->count())->toBe(0);
    expect(StoreProfitDistribution::count())->toBe(0);
    expect(PairEntry::whereHas('sourcePayment', fn ($q) => $q->where('id', $payment->id))->count())->toBe(0);

    $this->actingAs($member->user)->get('/member/emi')->assertInertia(fn ($page) => $page
        ->where('store_emi.status', 'active')
        ->where('store_emi.full_payment.amount', 31500)
        ->has('store_emi.installments', 10));

    $this->actingAs($member->user)->post("/member/emi/store/{$booking->id}/pay-all", ['mode' => 'cash'])->assertSessionHasNoErrors();
    $full = Payment::where('member_id', $member->id)->where('covers_installments', 9)->firstOrFail();
    expect((float) $full->amount)->toBe(31500.0);
    $this->actingAs(sebSuperAdmin())->post("/super-admin/cash-payments/{$full->id}/approve");

    expect($booking->fresh()->status)->toBe('completed');
    expect($booking->fresh()->emiSchedule->installments()->where('status', '!=', 'paid')->count())->toBe(0);
});

test('TEST.md scenario 40: 20 EMIs run from ₹2,100 down to ₹1,767.50', function () {
    ['anklet' => $anklet] = sebStore('SEB20');

    $quote = app(QuoteStoreEmiBooking::class)($anklet, 20);

    expect($quote['installment_amounts'])->toHaveCount(20);
    expect($quote['installment_amounts'][0])->toBe(2100.0);
    expect($quote['installment_amounts'][19])->toBe(1767.5);
});

test('a second open request, a wrong EMI count, another store\'s piece, an out-of-stock piece or an unknown member is refused', function () {
    ['admin' => $admin, 'anklet' => $anklet] = sebStore('SEBG');
    ['anklet' => $otherStorePiece] = sebStore('SEBO');
    $member = sebMember('SEBG-M');
    sebRequest($admin, $member, $anklet);

    $this->actingAs($admin)->post('/admin/store-emi', ['customer_id' => $member->customer_id, 'inventory_item_id' => $anklet->id, 'installment_count' => 10])
        ->assertSessionHasErrors('customer_id');

    $other = sebMember('SEBG-M2');
    $this->actingAs($admin)->post('/admin/store-emi', ['customer_id' => $other->customer_id, 'inventory_item_id' => $anklet->id, 'installment_count' => 15])
        ->assertSessionHasErrors('installment_count');
    $this->actingAs($admin)->post('/admin/store-emi', ['customer_id' => $other->customer_id, 'inventory_item_id' => $otherStorePiece->id, 'installment_count' => 10])
        ->assertSessionHasErrors('inventory_item_id');
    $this->actingAs($admin)->post('/admin/store-emi', ['customer_id' => 'NOBODY', 'inventory_item_id' => $anklet->id, 'installment_count' => 10])
        ->assertSessionHasErrors('customer_id');

    $anklet->update(['quantity' => 0]);
    $this->actingAs($admin)->post('/admin/store-emi', ['customer_id' => $other->customer_id, 'inventory_item_id' => $anklet->id, 'installment_count' => 10])
        ->assertSessionHasErrors('inventory_item_id');
});

test('a Store Admin cannot approve, the company Admin can, and a cancel needs a message and holds nothing', function () {
    ['admin' => $storeAdmin, 'anklet' => $anklet] = sebStore('SEBA');
    $booking = sebRequest($storeAdmin, sebMember('SEBA-M'), $anklet);

    $this->actingAs($storeAdmin)->post("/super-admin/store-emi-bookings/{$booking->id}/approve")->assertForbidden();

    $this->actingAs(sebSuperAdmin())->post("/super-admin/store-emi-bookings/{$booking->id}/cancel", ['cancel_message' => ''])
        ->assertSessionHasErrors('cancel_message');
    $this->actingAs(sebSuperAdmin())->post("/super-admin/store-emi-bookings/{$booking->id}/cancel", ['cancel_message' => 'Piece reserved for another order.']);
    expect($booking->fresh()->status)->toBe('cancelled');
    expect($anklet->fresh()->quantity)->toBe(2);

    $second = sebRequest($storeAdmin, sebMember('SEBA-M2'), $anklet);
    $companyAdmin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($companyAdmin)->post("/super-admin/store-emi-bookings/{$second->id}/approve")->assertSessionHasNoErrors();
    expect($second->fresh()->status)->toBe('active');
});

test('the Store Admin page lists the store\'s pieces with 10/20 estimates and its requests; the Super Admin queue shows today\'s quote', function () {
    ['admin' => $admin, 'anklet' => $anklet] = sebStore('SEBV');
    $booking = sebRequest($admin, sebMember('SEBV-M'), $anklet, 20);

    $this->actingAs($admin)->get('/admin/store-emi')->assertInertia(fn ($page) => $page
        ->component('admin/store-emi')
        ->where('items.0.id', $anklet->id)
        ->where('items.0.estimates.10.first_installment', 3850)
        ->where('items.0.estimates.20.first_installment', 2100)
        ->where('bookings.0.id', $booking->id)
        ->where('bookings.0.status', 'pending'));

    $this->actingAs(sebSuperAdmin())->get('/super-admin/store-emi-bookings')->assertInertia(fn ($page) => $page
        ->component('super-admin/store-emi-bookings')
        ->where('pending.0.id', $booking->id)
        ->where('pending.0.quote.total_value', 35000)
        ->where('pending.0.quote.first_installment', 2100)
        ->where('pending.0.blocked_reason', null));

    $this->actingAs($booking->member->user)->get('/member/emi')->assertInertia(fn ($page) => $page
        ->where('store_emi.status', 'pending')
        ->where('store_emi.installments', []));
});

test('an overdue store EMI holds the member\'s earnings like a plan EMI (scenario 38)', function () {
    ['admin' => $admin, 'anklet' => $anklet] = sebStore('SEBH');
    $member = sebMember('SEBH-M');
    $booking = sebRequest($admin, $member, $anklet);
    $this->actingAs(sebSuperAdmin())->post("/super-admin/store-emi-bookings/{$booking->id}/approve");
    EmiInstallment::where('emi_schedule_id', $booking->fresh()->emi_schedule_id)->where('installment_no', 1)->update(['status' => 'overdue']);

    $downline = sebMember('SEBH-P', $member);
    app(CalculateLevelIncome::class)(Payment::where('member_id', $downline->id)->firstOrFail());

    expect(WalletLedgerEntry::where('member_id', $member->id)->where('status', 'pending')->count())->toBe(1);
    expect((float) $member->fresh()->wallet_balance)->toBe(0.0);
});

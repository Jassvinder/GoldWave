<?php

use App\Actions\Payments\ApproveCashPayment;
use App\Actions\Payments\InitiateEmiInstallmentPayment;
use App\Actions\Payments\InitiateFullEmiPayment;
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
use App\Models\RuleValue;
use App\Models\Store;
use App\Models\StoreEmiBooking;
use App\Models\StoreInventoryItem;
use App\Models\StoreProfitDistribution;
use App\Models\StoreRestockShipment;
use App\Models\StoreSale;
use App\Models\User;
use App\Services\EarningsVerifier;
use Illuminate\Support\Facades\DB;

/**
 * T-185c (30-09-2026) — Docs/TEST.md scenario 42: handing a Repurchase on EMI over — the fully paid piece (the member pays
 * only GST), or the silver owed after a break (a piece of at least that weight; the member pays the extra grams, making
 * and GST). Neither generates income; each owes the store a restock of the prepaid metal.
 */
function sedOperator(): User
{
    return User::where('role', 'super_admin')->firstOrFail();
}

function sedMember(string $customerId): Member
{
    $member = Member::create([
        'user_id' => User::factory()->create(['role' => 'member'])->id,
        'customer_id' => $customerId,
        'membership_plan_id' => MembershipPlan::where('code', 'E')->value('id'),
        'status' => 'active',
        'activated_at' => now(),
    ]);
    Payment::create(['member_id' => $member->id, 'type' => 'registration', 'amount' => 20000, 'mode' => 'cash', 'status' => 'paid', 'paid_at' => now()]);

    return $member;
}

/** @return array{admin: User, store: Store, anklet: StoreInventoryItem, booking: StoreEmiBooking} */
function sedActive(Member $member, string $prefix): array
{
    $admin = User::factory()->create(['role' => 'store_admin']);
    $store = app(CreateStore::class)("{$prefix} Store", $admin, '9998887777', 'Test City', 1000000, 0, sedOperator());
    $anklet = app(AllocateStoreInventoryItem::class)($store, 'Silver Anklet', 'silver', 100, 2, 35000, sedOperator());
    $booking = app(RequestStoreEmiBooking::class)($store, $admin, $member, $anklet, 10);
    app(DecideStoreEmiBooking::class)->approve($booking, sedOperator());

    return ['admin' => $admin, 'store' => $store, 'anklet' => $anklet, 'booking' => $booking->fresh()];
}

function sedPay(Member $member, StoreEmiBooking $booking, int $no): void
{
    $installment = EmiInstallment::where('emi_schedule_id', $booking->emi_schedule_id)->where('installment_no', $no)->firstOrFail();
    $installment->update(['status' => 'due']);
    app(ApproveCashPayment::class)(app(InitiateEmiInstallmentPayment::class)($member->fresh(), $installment, 'cash'), sedOperator());
}

beforeEach(function () {
    $this->seed(); // silver ₹350/g, 0% making
    DB::table('rule_values')->where('key', 'level_income_min_directs')->update(['value' => json_encode([])]);
    RuleValue::where('key', 'store_gst_percent')->update(['value' => 3]);
});

test('TEST.md scenario 42: the fully paid piece is billed at the locked ₹35,000 + GST ₹1,050, ₹35,000 prepaid, ₹1,050 collected, no income', function () {
    $member = sedMember('SED-M');
    ['admin' => $admin, 'anklet' => $anklet, 'booking' => $booking] = sedActive($member, 'SED');
    sedPay($member, $booking, 1);
    app(ApproveCashPayment::class)(app(InitiateFullEmiPayment::class)($member->fresh(), 'cash', $booking->emiSchedule), sedOperator());
    expect($booking->fresh()->status)->toBe('completed');
    $stockBefore = $anklet->fresh()->quantity;

    $this->actingAs($admin)->post("/admin/store-emi/{$booking->id}/deliver-piece")
        ->assertRedirect('/admin/sales')
        ->assertSessionHas('status', fn ($status) => str_contains($status, 'Collect ₹1,050.00'));

    $sale = StoreSale::where('store_emi_booking_id', $booking->id)->firstOrFail();
    expect($sale->transaction_type)->toBe('repurchase');
    expect((float) $sale->rate)->toBe(350.0);
    expect((float) $sale->metal_value)->toBe(35000.0);
    expect((float) $sale->gst_amount)->toBe(1050.0);
    expect((float) $sale->total_invoice_amount)->toBe(36050.0);
    expect((float) $sale->prepaid_amount)->toBe(35000.0);
    expect($anklet->fresh()->quantity)->toBe($stockBefore);
    expect($booking->fresh()->status)->toBe('delivered');
    expect(IncomeLedgerCalculation::where('source_store_sale_id', $sale->id)->count())->toBe(0);
    expect(StoreProfitDistribution::where('store_sale_id', $sale->id)->count())->toBe(0);

    $restock = StoreRestockShipment::latest('id')->firstOrFail();
    expect((float) $restock->weight)->toBe(100.0);
    expect((float) $restock->value)->toBe(35000.0);

    // The bill shows what the EMIs paid and what was paid at delivery.
    $this->actingAs($admin)->post("/admin/sales/{$sale->id}/bill");
    $this->actingAs($admin)->get("/admin/sales/{$sale->id}/invoice")->assertInertia(fn ($page) => $page
        ->where('invoice.prepaid_amount', '35000.00')
        ->where('invoice.amount_due', '1050.00'));

    // Closed — the member can ask for a new Repurchase on EMI.
    expect(StoreEmiBooking::where('member_id', $member->id)->open()->exists())->toBeFalse();
    expect(app(EarningsVerifier::class)->run(['purchase', 'store'])['errors'])->toBe(0);
});

test('TEST.md scenario 42: break silver — a 15 g piece is refused, a 20 g piece is billed ₹9,064 with ₹7,000 prepaid (17.5 g × ₹400) and ₹2,064 collected', function () {
    $member = sedMember('SED-B');
    ['admin' => $admin, 'store' => $store, 'booking' => $booking] = sedActive($member, 'SEDB');
    sedPay($member, $booking, 1);
    MetalRate::create(['metal' => 'silver', 'rate_per_gram' => 400, 'making_charge_percent' => 10, 'effective_from' => now()->toDateString(), 'created_by' => sedOperator()->id]);
    sedPay($member, $booking, 2);
    EmiInstallment::where('emi_schedule_id', $booking->emi_schedule_id)->whereIn('installment_no', [3, 4, 5])->update(['status' => 'overdue']);
    (new ProcessEmiDueStatuses)->handle();
    expect((float) $booking->fresh()->silver_grams_owed)->toBe(17.5);

    $light = app(AllocateStoreInventoryItem::class)($store, 'Silver Coin 15g', 'silver', 15, 1, 6000, sedOperator());
    $heavy = app(AllocateStoreInventoryItem::class)($store, 'Silver Coin 20g', 'silver', 20, 2, 8000, sedOperator());

    $this->actingAs($admin)->post("/admin/store-emi/{$booking->id}/deliver-silver", ['inventory_item_id' => $light->id])
        ->assertSessionHasErrors('inventory_item_id');
    expect($booking->fresh()->status)->toBe('broken');

    $this->actingAs($admin)->post("/admin/store-emi/{$booking->id}/deliver-silver", ['inventory_item_id' => $heavy->id])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', fn ($status) => str_contains($status, 'Collect ₹2,064.00'));

    $sale = StoreSale::where('store_emi_booking_id', $booking->id)->firstOrFail();
    expect((float) $sale->metal_value)->toBe(8000.0);
    expect((float) $sale->making_charges)->toBe(800.0);
    expect((float) $sale->gst_amount)->toBe(264.0);
    expect((float) $sale->total_invoice_amount)->toBe(9064.0);
    expect((float) $sale->prepaid_amount)->toBe(7000.0);
    expect($heavy->fresh()->quantity)->toBe(1);
    expect($booking->fresh()->status)->toBe('delivered');
    expect(IncomeLedgerCalculation::where('source_store_sale_id', $sale->id)->count())->toBe(0);

    $restock = StoreRestockShipment::latest('id')->firstOrFail();
    expect((float) $restock->weight)->toBe(17.5);
    expect((float) $restock->value)->toBe(7000.0);
});

test('a booking cannot be handed over in the wrong status or by another store', function () {
    $member = sedMember('SED-G');
    ['admin' => $admin, 'booking' => $booking] = sedActive($member, 'SEDG');

    // Still running (active): no handover.
    $this->actingAs($admin)->post("/admin/store-emi/{$booking->id}/deliver-piece")->assertSessionHasErrors('booking');

    ['admin' => $otherAdmin] = sedActive(sedMember('SED-G2'), 'SEDG2');
    $booking->update(['status' => 'completed']);
    $this->actingAs($otherAdmin)->post("/admin/store-emi/{$booking->id}/deliver-piece")->assertSessionHasErrors('booking');
    expect($booking->fresh()->status)->toBe('completed');
});

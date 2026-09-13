<?php

use App\Models\EmiSchedule;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\FakePaymentGateway;
use Illuminate\Support\Facades\Hash;

/**
 * DOMAIN_LOGIC.md §2.1/§3/§3.0/§4.3, Docs/TEST.md scenarios 9 and 10.
 */
function createActiveMember(string $customerId, ?int $sponsorId = null, ?int $placementParentId = null, ?string $placementSide = null): Member
{
    $user = User::factory()->create(['role' => 'member']);

    return Member::create([
        'user_id' => $user->id,
        'customer_id' => $customerId,
        'sponsor_id' => $sponsorId,
        'placement_parent_id' => $placementParentId,
        'placement_side' => $placementSide,
        'status' => 'active',
        'activated_at' => now(),
    ]);
}

beforeEach(function () {
    $this->seed();
});

test('an invalid sponsor code is rejected', function () {
    $response = $this->postJson('/join/validate-sponsor', ['sponsor_code' => 'GWL99']);

    $response->assertStatus(422)->assertJson(['valid' => false]);
});

test('a valid but inactive sponsor is rejected exactly like an invalid code, and re-validates once active', function () {
    $sponsor = createActiveMember('GWL900');
    $sponsor->update(['status' => 'payment_pending']);

    $this->postJson('/join/validate-sponsor', ['sponsor_code' => 'GWL900'])
        ->assertStatus(422)
        ->assertJson(['valid' => false]);

    $sponsor->update(['status' => 'active']);

    $this->postJson('/join/validate-sponsor', ['sponsor_code' => 'GWL900'])
        ->assertOk()
        ->assertJson(['valid' => true, 'sponsor_customer_id' => 'GWL900']);
});

test('binary placement walks the occupied side straight down regardless of depth', function () {
    $s = createActiveMember('GWL900');
    $l1 = createActiveMember('GWL901', sponsorId: $s->id, placementParentId: $s->id, placementSide: 'left');
    $l2 = createActiveMember('GWL902', sponsorId: $s->id, placementParentId: $l1->id, placementSide: 'left');
    // S's Right is empty; L2's Left is empty — the new member must land at L2's Left, not S's Right.

    $plan = MembershipPlan::where('code', 'F')->first();

    $this->post('/join', [
        'sponsor_code' => 'GWL900',
        'placement_side' => 'left',
        'name' => 'New Member',
        'email' => 'newmember@example.test',
        'mobile' => '9876543210',
        'membership_plan_id' => $plan->id,
        'payment_mode' => 'cash',
    ])->assertRedirect();

    $newMember = Member::where('sponsor_id', $s->id)
        ->whereNotIn('id', [$l1->id, $l2->id])
        ->firstOrFail();

    expect($newMember->placement_parent_id)->toBe($l2->id);
    expect($newMember->placement_side)->toBe('left');
    expect($newMember->sponsor_id)->toBe($s->id); // Sponsor/Direct unaffected by placement depth (§0/§4).
});

test('Current Rate Booking computes the exact worked example for Plan A', function () {
    $sponsor = createActiveMember('GWL900');
    $plan = MembershipPlan::where('code', 'A')->first();

    $this->post('/join', [
        'sponsor_code' => 'GWL900',
        'placement_side' => 'left',
        'name' => 'Current Rate Member',
        'email' => 'currentrate@example.test',
        'mobile' => '9876500001',
        'membership_plan_id' => $plan->id,
        'rate_booking_method' => 'current_rate',
        'payment_mode' => 'cash',
    ])->assertRedirect();

    $member = Member::whereHas('user', fn ($q) => $q->where('email', 'currentrate@example.test'))->firstOrFail();
    $schedule = EmiSchedule::where('member_id', $member->id)->firstOrFail();

    // §3.0 worked example: 100gm silver = 10 tola @ ₹3,500/tola (= ₹350/gram, seeded) → ₹35,000 total,
    // ₹350 maintenance, EMI = 35000/20 + 350 = ₹2,100/month.
    expect((float) $schedule->installment_amount)->toBe(2100.0);
    expect((float) $schedule->maintenance_cost)->toBe(350.0);

    $payment = Payment::where('member_id', $member->id)->where('type', 'registration')->firstOrFail();
    expect((float) $payment->amount)->toBe(2100.0);
});

test('Future Rate Booking uses the plan base amount with no maintenance cost', function () {
    createActiveMember('GWL900');
    $plan = MembershipPlan::where('code', 'D')->first();

    $this->post('/join', [
        'sponsor_code' => 'GWL900',
        'placement_side' => 'right',
        'name' => 'Future Rate Member',
        'email' => 'futurerate@example.test',
        'mobile' => '9876500002',
        'membership_plan_id' => $plan->id,
        'rate_booking_method' => 'future_rate',
        'payment_mode' => 'cash',
    ])->assertRedirect();

    $member = Member::whereHas('user', fn ($q) => $q->where('email', 'futurerate@example.test'))->firstOrFail();
    $schedule = EmiSchedule::where('member_id', $member->id)->firstOrFail();

    expect((float) $schedule->installment_amount)->toBe(10000.0);
    expect($schedule->maintenance_cost)->toBeNull();
    expect((float) $schedule->future_commitment_amount)->toBe(100000.0); // ₹10,000 × 10
});

test('cash registration stays inactive until Super Admin approves, then activates with a sequential Customer ID and password = Customer ID', function () {
    createActiveMember('GWL900');
    $plan = MembershipPlan::where('code', 'F')->first();

    $this->post('/join', [
        'sponsor_code' => 'GWL900',
        'placement_side' => 'left',
        'name' => 'Cash Member',
        'email' => 'cashmember@example.test',
        'mobile' => '9876500003',
        'membership_plan_id' => $plan->id,
        'payment_mode' => 'cash',
    ])->assertRedirect();

    $member = Member::whereHas('user', fn ($q) => $q->where('email', 'cashmember@example.test'))->firstOrFail();
    expect($member->status)->toBe('payment_pending');
    expect($member->customer_id)->toBeNull();

    $payment = Payment::where('member_id', $member->id)->firstOrFail();
    expect($payment->cash_status)->toBe('pending_verification');

    $superAdmin = User::where('role', 'super_admin')->firstOrFail();

    $this->actingAs($superAdmin)
        ->post("/super-admin/cash-payments/{$payment->id}/approve")
        ->assertRedirect();

    $member->refresh();
    expect($member->status)->toBe('active');
    expect($member->customer_id)->not->toBeNull();
    expect($member->activated_by)->toBe($superAdmin->id);

    expect(Hash::check($member->customer_id, $member->user->fresh()->password))->toBeTrue();
});

test('rejecting a cash payment leaves the member inactive with an audit trail and no compensation', function () {
    createActiveMember('GWL900');
    $plan = MembershipPlan::where('code', 'F')->first();

    $this->post('/join', [
        'sponsor_code' => 'GWL900',
        'placement_side' => 'left',
        'name' => 'Rejected Member',
        'email' => 'rejected@example.test',
        'mobile' => '9876500004',
        'membership_plan_id' => $plan->id,
        'payment_mode' => 'cash',
    ])->assertRedirect();

    $member = Member::whereHas('user', fn ($q) => $q->where('email', 'rejected@example.test'))->firstOrFail();
    $payment = Payment::where('member_id', $member->id)->firstOrFail();

    $superAdmin = User::where('role', 'super_admin')->firstOrFail();

    $this->actingAs($superAdmin)
        ->post("/super-admin/cash-payments/{$payment->id}/reject")
        ->assertRedirect();

    $member->refresh();
    $payment->refresh();

    expect($member->status)->toBe('payment_pending');
    expect($payment->status)->toBe('failed');
    expect($payment->cash_status)->toBe('rejected');
});

test('the online payment webhook is idempotent — a duplicate callback never re-activates or duplicates a Customer ID', function () {
    createActiveMember('GWL900');
    $plan = MembershipPlan::where('code', 'F')->first();

    $this->post('/join', [
        'sponsor_code' => 'GWL900',
        'placement_side' => 'left',
        'name' => 'Online Member',
        'email' => 'onlinemember@example.test',
        'mobile' => '9876500005',
        'membership_plan_id' => $plan->id,
        'payment_mode' => 'online',
    ])->assertRedirect();

    $member = Member::whereHas('user', fn ($q) => $q->where('email', 'onlinemember@example.test'))->firstOrFail();
    $payment = Payment::where('member_id', $member->id)->firstOrFail();

    $gateway = app(FakePaymentGateway::class);
    $reference = 'FAKE-TEST-REF';
    $signature = $gateway->sign($payment->id, $reference);

    $callback = ['payment_id' => $payment->id, 'reference' => $reference, 'signature' => $signature];

    $this->postJson('/payments/webhook', $callback)->assertOk();

    $member->refresh();
    expect($member->status)->toBe('active');
    $firstCustomerId = $member->customer_id;

    // Duplicate/retried webhook.
    $this->postJson('/payments/webhook', $callback)->assertOk();

    $member->refresh();
    expect($member->customer_id)->toBe($firstCustomerId);
    expect(Member::where('customer_id', $firstCustomerId)->count())->toBe(1);
});

test('an invalid webhook signature is rejected and never activates the membership', function () {
    createActiveMember('GWL900');
    $plan = MembershipPlan::where('code', 'F')->first();

    $this->post('/join', [
        'sponsor_code' => 'GWL900',
        'placement_side' => 'left',
        'name' => 'Tampered Member',
        'email' => 'tampered@example.test',
        'mobile' => '9876500006',
        'membership_plan_id' => $plan->id,
        'payment_mode' => 'online',
    ])->assertRedirect();

    $member = Member::whereHas('user', fn ($q) => $q->where('email', 'tampered@example.test'))->firstOrFail();
    $payment = Payment::where('member_id', $member->id)->firstOrFail();

    $this->postJson('/payments/webhook', [
        'payment_id' => $payment->id,
        'reference' => 'FAKE-BAD',
        'signature' => 'not-a-real-signature',
    ])->assertStatus(400);

    $member->refresh();
    expect($member->status)->toBe('payment_pending');
});

test('cash-payment approval is restricted to super_admin — a member cannot approve their own or anyone else\'s payment', function () {
    createActiveMember('GWL900');
    $plan = MembershipPlan::where('code', 'F')->first();

    $this->post('/join', [
        'sponsor_code' => 'GWL900',
        'placement_side' => 'left',
        'name' => 'Role Boundary Member',
        'email' => 'roleboundary@example.test',
        'mobile' => '9876500007',
        'membership_plan_id' => $plan->id,
        'payment_mode' => 'cash',
    ])->assertRedirect();

    $member = Member::whereHas('user', fn ($q) => $q->where('email', 'roleboundary@example.test'))->firstOrFail();
    $payment = Payment::where('member_id', $member->id)->firstOrFail();

    $plainMember = createActiveMember('GWL901');

    $this->actingAs($plainMember->user)
        ->post("/super-admin/cash-payments/{$payment->id}/approve")
        ->assertForbidden();

    $payment->refresh();
    expect($payment->status)->toBe('pending');
});

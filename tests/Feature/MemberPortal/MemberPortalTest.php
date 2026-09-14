<?php

use App\Models\EmiSchedule;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * INSTRUCTIONS.md's Member Portal (T-015) — the HTTP layer (routes, Form
 * Requests, file uploads) that M02-M05/M07/M10-M18 needed but never had
 * before this task (their Actions were built backend-only in earlier tasks,
 * exercised only by directly invoking the Action in tests).
 */
function portalMember(string $customerId, ?MembershipPlan $plan = null): Member
{
    $user = User::factory()->create(['role' => 'member']);

    return Member::create([
        'user_id' => $user->id,
        'customer_id' => $customerId,
        'status' => 'active',
        'activated_at' => now(),
        'membership_plan_id' => $plan?->id,
    ]);
}

beforeEach(function () {
    $this->seed();
    Storage::fake('public');
});

test('a guest is redirected to login for any member portal route', function () {
    $this->get('/member/wallet')->assertRedirect('/login');
});

test('a member sees their dashboard with real data, not the generic placeholder', function () {
    $member = portalMember('DASH-1');

    $response = $this->actingAs($member->user)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('member/dashboard')
        ->where('member.customer_id', 'DASH-1'));
});

test('a super admin sees the real S01 System Dashboard, not the member one', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);

    $response = $this->actingAs($admin)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('super-admin/dashboard'));
});

test('a member can submit Pending Profile fields exactly once, with real file uploads', function () {
    $member = portalMember('PEND-1');

    $payload = [
        'pan_card' => 'ABCDE1234F',
        'aadhaar_card' => '123456789012',
        'profile_photo' => UploadedFile::fake()->image('photo.jpg'),
        'address' => '123 Test Street',
        'bank_account_holder_name' => 'Test Holder',
        'bank_account_number' => '1234567890',
        'bank_ifsc_code' => 'TEST0001234',
        'bank_name' => 'Test Bank',
        'bank_proof_document' => UploadedFile::fake()->create('cheque.pdf', 100, 'application/pdf'),
    ];

    $this->actingAs($member->user)
        ->post('/member/pending-profile', $payload)
        ->assertRedirect('/member/profile');

    $fresh = $member->fresh();
    expect($fresh->pending_fields_submitted_at)->not->toBeNull();
    expect($fresh->pan_card)->toBe('ABCDE1234F');
    Storage::disk('public')->assertExists($fresh->profile_photo_path);

    $bankDetail = $fresh->bankDetails()->latest('id')->first();
    Storage::disk('public')->assertExists($bankDetail->proof_document_path);

    // A second submission is rejected — fields lock after the first.
    $this->actingAs($member->user)
        ->post('/member/pending-profile', $payload)
        ->assertSessionHasErrors('pending_fields');

    expect($fresh->fresh()->pan_card)->toBe('ABCDE1234F');
});

test('an invalid PAN format is rejected before anything is saved', function () {
    $member = portalMember('PEND-2');

    $this->actingAs($member->user)
        ->post('/member/pending-profile', [
            'pan_card' => 'not-a-pan',
            'aadhaar_card' => '123456789012',
            'profile_photo' => UploadedFile::fake()->image('photo.jpg'),
            'address' => '123 Test Street',
            'bank_account_holder_name' => 'Test Holder',
            'bank_account_number' => '1234567890',
            'bank_ifsc_code' => 'TEST0001234',
            'bank_name' => 'Test Bank',
            'bank_proof_document' => UploadedFile::fake()->create('cheque.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('pan_card');

    expect($member->fresh()->pending_fields_submitted_at)->toBeNull();
});

test('a member cannot submit a Change Request before Pending Profile fields are submitted, and can after', function () {
    $member = portalMember('CHG-1');

    $this->actingAs($member->user)
        ->post('/member/change-requests', [
            'field_name' => 'address',
            'new_value' => 'New Address',
        ])
        ->assertSessionHasErrors('pending_fields');

    $member->update(['pending_fields_submitted_at' => now(), 'address' => 'Old Address']);

    // Re-authenticate with a freshly fetched user: SessionGuard memoizes the
    // resolved user (and its loaded relations) for the lifetime of the test,
    // so re-using the original $member->user object here would still see
    // the pre-update Member relation cached from the request above.
    $this->actingAs($member->fresh()->user)
        ->post('/member/change-requests', [
            'field_name' => 'address',
            'new_value' => 'New Address',
        ])
        ->assertRedirect('/member/change-requests');

    $request = $member->profileChangeRequests()->latest('id')->first();
    expect($request->old_value)->toBe('Old Address');
    expect($request->new_value)->toBe('New Address');
    expect($request->status)->toBe('pending');

    // The field itself is untouched until Super Admin reviews it.
    expect($member->fresh()->address)->toBe('Old Address');
});

test('a payout request is blocked without verified bank details, and succeeds once verified', function () {
    $member = portalMember('PAY-1');
    $member->update(['wallet_balance' => 5000, 'pending_fields_submitted_at' => now()]);

    $this->actingAs($member->user)
        ->post('/member/payout', ['amount' => 1000])
        ->assertSessionHasErrors('amount');

    $bankDetail = $member->bankDetails()->create([
        'account_holder_name' => 'Test Holder',
        'account_number' => '1234567890',
        'ifsc_code' => 'TEST0001234',
        'bank_name' => 'Test Bank',
        'proof_document_path' => 'proofs/x.jpg',
    ]);

    // Unverified bank detail also blocks the request, per SubmitPayoutRequest.
    $this->actingAs($member->user)
        ->post('/member/payout', ['amount' => 1000])
        ->assertSessionHasErrors('bank_detail');

    $bankDetail->update(['verified_at' => now()]);

    $this->actingAs($member->user)
        ->post('/member/payout', ['amount' => 1000])
        ->assertRedirect('/member/payout');

    expect($member->payoutRequests()->count())->toBe(1);
    expect((float) $member->fresh()->wallet_hold_amount)->toBe(1000.0);
});

test('the EMI page shows the Pair/Reward eligibility indicator for an EMI plan member', function () {
    $plan = MembershipPlan::where('code', 'A')->firstOrFail();
    $member = portalMember('EMI-1', $plan);

    EmiSchedule::create([
        'member_id' => $member->id,
        'membership_plan_id' => $plan->id,
        'total_installments' => $plan->installment_count,
        'rate_booking_method' => 'current_rate',
        'installment_amount' => $plan->amount,
    ]);

    $response = $this->actingAs($member->user)->get('/member/emi');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('member/emi')
        ->where('pair_eligibility.required_emis', 6)
        ->where('pair_eligibility.completed_emis', 0)
        ->where('pair_eligibility.eligible', false));
});

test('a member can view their own Wallet, Level Income, Pair/Reward, Booster, Draw, Payment History, and Notifications pages', function () {
    $member = portalMember('NAV-1');
    $actingAs = $this->actingAs($member->user);

    $actingAs->get('/member/wallet')->assertOk();
    $actingAs->get('/member/level-income')->assertOk();
    $actingAs->get('/member/pair-reward')->assertOk();
    $actingAs->get('/member/booster')->assertOk();
    $actingAs->get('/member/draw')->assertOk();
    $actingAs->get('/member/payment-history')->assertOk();
    $actingAs->get('/member/notifications')->assertOk();
    $actingAs->get('/member/membership')->assertOk();
    $actingAs->get('/member/reports')->assertOk();
});

test('a member can download their own payment history report as CSV', function () {
    $member = portalMember('REPORT-1');

    $response = $this->actingAs($member->user)->get('/member/reports/payments/export');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

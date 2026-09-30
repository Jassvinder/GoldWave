<?php

use App\Models\CompanyWallet;
use App\Models\CompanyWalletLedgerEntry;
use App\Models\EmiSchedule;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Notifications\BankDetailsVerified;
use App\Services\WalletLedgerService;
use App\Support\Portal;
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

test('a guest is redirected to the Member login for any member portal route (T-198)', function () {
    $this->get('/member/wallet')->assertRedirect('/member/login');
});

test('after a member session ends, shared pages send them to the Member login; staff pages keep the staff login (T-198)', function () {
    // /dashboard is shared by every portal — the remembered door decides.
    $this->withCookie(Portal::COOKIE, Portal::MEMBER)->get('/dashboard')->assertRedirect('/member/login');
    $this->withCookie(Portal::COOKIE, Portal::STAFF)->get('/dashboard')->assertRedirect('/login');
    $this->get('/super-admin/payout-requests')->assertRedirect('/login');
});

test('a member who logs out lands on the Member login; Super Admin still goes home (T-198)', function () {
    $member = portalMember('LOGOUT-1');

    $this->actingAs($member->user)->withCookie(Portal::COOKIE, Portal::MEMBER)
        ->post('/logout')->assertRedirect('/member/login');

    $this->actingAs(User::factory()->create(['role' => 'super_admin']))->withCookie(Portal::COOKIE, Portal::STAFF)
        ->post('/logout')->assertRedirect('/');
});

test('a member sees their dashboard with real data, not the generic placeholder', function () {
    $member = portalMember('DASH-1');

    $response = $this->actingAs($member->user)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('member/dashboard')
        ->where('member.customer_id', 'DASH-1')
        // T-194 — team card numbers (a leaf member has no team).
        ->where('team', ['direct' => 0, 'left' => 0, 'right' => 0, 'total' => 0]));

    // Directs View and Tree View carry the same team numbers.
    $this->actingAs($member->user)->get('/member/directs')
        ->assertInertia(fn ($page) => $page->where('team.total', 0)->where('team.direct', 0));
    $this->actingAs($member->user)->get('/member/tree')
        ->assertInertia(fn ($page) => $page->where('team.total', 0));
});

test('the dashboard income total itemises every income type and reconciles with the wallet', function () {
    $member = portalMember('DASH-2');
    $wallet = app(WalletLedgerService::class);

    $wallet->credit($member, 'level_income', 9000, null, 'Level income');
    $wallet->credit($member, 'pair_reward', 5500, null, 'Pair/Reward milestone');
    $wallet->debit($member, 'assisted_registration', 1500, null, 'Registered a member');

    $this->actingAs($member->user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('income.total', '14500.00')
            ->where('income.level_income', '9000.00')
            ->where('income.pair_reward', '5500.00')
            ->where('wallet.used_for_registrations', '1500.00')
            ->where('wallet.balance', '13000.00'));
});

test('the Income Booster card lists what each qualified level has paid so far (01-10-2026)', function () {
    $member = portalMember('DASH-BOOST');
    $ruleVersionId = App\Models\RuleVersion::where('is_active', true)->value('id');

    foreach ([1 => [5000, 5000, 5000], 2 => [20000, 20000]] as $level => $months) {
        $qualification = App\Models\BoosterQualification::create(['member_id' => $member->id, 'level_no' => $level, 'qualified_at' => now(), 'rule_version_id' => $ruleVersionId]);

        foreach ($months as $i => $amount) {
            App\Models\BoosterPayoutSchedule::create([
                'booster_qualification_id' => $qualification->id,
                'month_no' => $i + 1,
                'scheduled_date' => now()->addMonths($i)->toDateString(),
                'amount' => $amount,
                // Level 1 has paid 2 of 3 months, level 2 one of 2.
                'status' => $i < ($level === 1 ? 2 : 1) ? 'paid' : 'pending',
            ]);
        }
    }

    $this->actingAs($member->user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('booster_levels.0', ['level_no' => 1, 'received' => '10000.00', 'months_paid' => 2, 'months_total' => 3])
            ->where('booster_levels.1', ['level_no' => 2, 'received' => '20000.00', 'months_paid' => 1, 'months_total' => 2]));
});

test('a super admin sees the real S01 System Dashboard, not the member one', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);

    $response = $this->actingAs($admin)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('super-admin/dashboard'));
});

test("a member's own profile page shows their sponsor's Customer ID and name (T-121)", function () {
    $sponsor = portalMember('SPON-1');
    $member = portalMember('SPON-2');
    $member->update(['sponsor_id' => $sponsor->id]);

    $this->actingAs($member->user)
        ->get('/member/profile')
        ->assertInertia(fn ($page) => $page
            ->component('member/profile')
            ->where('member.sponsor_customer_id', 'SPON-1')
            ->where('member.sponsor_name', $sponsor->user->name));
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

    // T-195 — Super Admin sees the member waiting for bank verification on Payout Requests…
    $admin = User::factory()->create(['role' => 'super_admin']);
    $this->actingAs($admin)->get('/super-admin/payout-requests')
        ->assertInertia(fn ($page) => $page->where('awaiting_bank_verification.0.customer_id', 'PAY-1'));

    // …verifies them on Member Detail, and the member is notified.
    $this->actingAs($admin)->post("/super-admin/members/{$member->id}/verify-bank-detail")->assertRedirect();
    expect($bankDetail->fresh()->verified_at)->not->toBeNull()
        ->and($member->user->notifications()->where('type', BankDetailsVerified::class)->exists())->toBeTrue();

    // Below the minimum and above the available balance are both refused.
    $this->actingAs($member->fresh()->user)->post('/member/payout', ['amount' => 499])->assertSessionHasErrors('amount');
    $this->actingAs($member->fresh()->user)->post('/member/payout', ['amount' => 5000.01])->assertSessionHasErrors('amount');

    $this->actingAs($member->fresh()->user)
        ->post('/member/payout', ['amount' => 1000])
        ->assertRedirect('/member/payout');

    expect($member->payoutRequests()->count())->toBe(1);
    expect((float) $member->fresh()->wallet_hold_amount)->toBe(1000.0);

    // The request reaches Super Admin's queue, and the member no longer waits on verification.
    $this->actingAs($admin)->get('/super-admin/payout-requests')
        ->assertInertia(fn ($page) => $page
            ->where('pending.0.member.customer_id', 'PAY-1')
            ->where('pending.0.requested_amount', '1000.00')
            ->where('awaiting_bank_verification', []));
});

test('a member can cancel their own pending payout request, releasing the wallet hold (T-147)', function () {
    $member = portalMember('PAY-CANCEL-1');
    $member->update(['wallet_balance' => 5000, 'pending_fields_submitted_at' => now()]);
    $bankDetail = $member->bankDetails()->create([
        'account_holder_name' => 'Test Holder',
        'account_number' => '1234567891',
        'ifsc_code' => 'TEST0001235',
        'bank_name' => 'Test Bank',
        'proof_document_path' => 'proofs/x.jpg',
        'verified_at' => now(),
    ]);

    $this->actingAs($member->user)->post('/member/payout', ['amount' => 1000]);
    $payoutRequest = $member->payoutRequests()->firstOrFail();
    expect((float) $member->fresh()->wallet_hold_amount)->toBe(1000.0);

    $this->actingAs($member->user)
        ->post("/member/payout/{$payoutRequest->id}/cancel")
        ->assertRedirect('/member/payout');

    expect($payoutRequest->fresh()->status)->toBe('cancelled');
    expect((float) $member->fresh()->wallet_hold_amount)->toBe(0.0);

    // A member cannot cancel someone else's payout request.
    $other = portalMember('PAY-CANCEL-2');
    $other->update(['wallet_balance' => 5000, 'pending_fields_submitted_at' => now()]);
    $otherBank = $other->bankDetails()->create([
        'account_holder_name' => 'Other Holder', 'account_number' => '9998887771', 'ifsc_code' => 'TEST0009991',
        'bank_name' => 'Test Bank', 'proof_document_path' => 'proofs/y.jpg', 'verified_at' => now(),
    ]);
    $this->actingAs($other->user)->post('/member/payout', ['amount' => 1000]);
    $othersRequest = $other->payoutRequests()->firstOrFail();

    $this->actingAs($member->user)
        ->post("/member/payout/{$othersRequest->id}/cancel")
        ->assertNotFound();

    expect($othersRequest->fresh()->status)->toBe('pending');

    // Already-cancelled request cannot be cancelled again.
    $this->actingAs($member->user)
        ->post("/member/payout/{$payoutRequest->id}/cancel")
        ->assertSessionHasErrors('payout_request');
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

test("a member's own profile page shows their gender (T-122)", function () {
    $member = portalMember('GENDER-1');
    $member->update(['gender' => 'male']);

    $this->actingAs($member->user)
        ->get('/member/profile')
        ->assertInertia(fn ($page) => $page->where('member.gender', 'male'));
});

// ---------------------------------------------------------------- T-153: Assisted Registration

test('a member has one permanent registration link that opens the join page with them as sponsor (T-162)', function () {
    $member = portalMember('REF-SPONSOR');

    $first = null;
    $this->actingAs($member->user)
        ->get('/member/register-new')
        ->assertInertia(function ($page) use (&$first) {
            $first = $page->toArray()['props']['referral_link'];
        });

    $code = $member->fresh()->referral_code;
    expect($code)->not->toBeNull()
        ->and($first)->toBe(route('registration.show', ['ref' => $code]))
        ->and($first)->not->toContain('REF-SPONSOR');

    // Same link every time.
    $this->actingAs($member->user)
        ->get('/member/register-new')
        ->assertInertia(fn ($page) => $page->where('referral_link', $first));
    expect($member->fresh()->referral_code)->toBe($code);

    $this->get("/join?ref={$code}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('registration/register')
            ->where('referral.sponsor_customer_id', 'REF-SPONSOR')
            ->where('referral_invalid', false));

    $this->get('/join?ref=not-a-real-code')
        ->assertInertia(fn ($page) => $page
            ->where('referral', null)
            ->where('referral_invalid', true));

    $this->get('/join')
        ->assertInertia(fn ($page) => $page
            ->where('referral', null)
            ->where('referral_invalid', false));
});

test('a member can register a different, new member and pay from their own wallet (T-153, DOMAIN_LOGIC.md §12.2(b))', function () {
    $sponsor = portalMember('AR-SPONSOR');
    $payer = portalMember('AR-PAYER');
    $payer->update(['wallet_balance' => 60000]);
    $plan = MembershipPlan::where('code', 'F')->firstOrFail();

    $this->actingAs($payer->user)
        ->get('/member/register-new')
        ->assertInertia(fn ($page) => $page->where('wallet_balance', 60000));

    $this->actingAs($payer->user)
        ->post('/member/register-new', [
            'sponsor_code' => $sponsor->customer_id,
            'placement_side' => 'left',
            'gender' => 'male',
            'name' => 'Assisted New Member',
            'email' => 'assisted-new@example.test',
            'mobile' => '9876511111',
            'membership_plan_id' => $plan->id,
            'payment_mode' => 'wallet',
        ])
        ->assertRedirect();

    $newMember = Member::where('sponsor_id', $sponsor->id)->firstOrFail();
    expect($newMember->status)->toBe('active');
    expect($newMember->customer_id)->not->toBeNull();

    $payment = $newMember->payments->firstWhere('type', 'registration');
    expect($payment->status)->toBe('paid');
    expect($payment->mode)->toBe('wallet');
    expect($payment->paying_member_id)->toBe($payer->id);
    expect($payment->paying_store_id)->toBeNull();

    expect((float) $payer->fresh()->wallet_balance)->toBe(10000.0); // 60,000 - 50,000.

    $companyWallet = CompanyWallet::firstOrFail();
    expect((float) $companyWallet->balance)->toBe(50000.0);
    expect(CompanyWalletLedgerEntry::where('company_wallet_id', $companyWallet->id)->where('category', 'assisted_registration')->exists())->toBeTrue();
});

test('assisted registration via wallet is blocked when the payer\'s wallet balance is insufficient', function () {
    $sponsor = portalMember('AR-SPONSOR2');
    $payer = portalMember('AR-POORPAYER');
    $payer->update(['wallet_balance' => 100]);
    $plan = MembershipPlan::where('code', 'F')->firstOrFail();

    $this->actingAs($payer->user)
        ->post('/member/register-new', [
            'sponsor_code' => $sponsor->customer_id,
            'placement_side' => 'right',
            'gender' => 'female',
            'name' => 'Blocked New Member',
            'email' => 'blocked-new@example.test',
            'mobile' => '9876522222',
            'membership_plan_id' => $plan->id,
            'payment_mode' => 'wallet',
        ])
        ->assertSessionHasErrors('amount');

    $newMember = Member::where('sponsor_id', $sponsor->id)->firstOrFail();
    $payment = $newMember->payments->firstWhere('type', 'registration');
    // The member row and its pending payment were already created by RegisterMember before the
    // wallet debit failed — this mirrors a normal cash/online registration's pending state.
    expect($payment->status)->toBe('pending');
    expect((float) $payer->fresh()->wallet_balance)->toBe(100.0);
});

test('assisted registration still supports cash, unaffected by the wallet option (T-153 regression)', function () {
    $sponsor = portalMember('AR-SPONSOR3');
    $payer = portalMember('AR-CASHPAYER');
    $plan = MembershipPlan::where('code', 'F')->firstOrFail();

    $this->actingAs($payer->user)
        ->post('/member/register-new', [
            'sponsor_code' => $sponsor->customer_id,
            'placement_side' => 'left',
            'gender' => 'male',
            'name' => 'Cash New Member',
            'email' => 'cash-new@example.test',
            'mobile' => '9876533333',
            'membership_plan_id' => $plan->id,
            'payment_mode' => 'cash',
        ])
        ->assertRedirect();

    $newMember = Member::where('sponsor_id', $sponsor->id)->firstOrFail();
    $payment = $newMember->payments->firstWhere('type', 'registration');
    expect($payment->status)->toBe('pending');
    expect($payment->mode)->toBe('cash');
    expect($payment->paying_member_id)->toBeNull();
});

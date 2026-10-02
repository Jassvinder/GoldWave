<?php

use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Services\RuleVersionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * T-196 (DOMAIN_LOGIC.md §10, 30-09-2026) — GPay/UPI with Ref ID + screenshot, approved by Super Admin or Admin like
 * cash; Online (Razorpay) switched off by default; the company UPI details come from Payment Settings.
 */
function upiSponsor(): Member
{
    return Member::create([
        'user_id' => User::factory()->create(['role' => 'member'])->id,
        'customer_id' => 'GWL950',
        'status' => 'active',
        'activated_at' => now(),
    ]);
}

/** @return array<string, mixed> */
function upiJoinPayload(array $overrides = []): array
{
    return array_merge([
        'sponsor_code' => 'GWL950',
        'placement_side' => 'left',
        'gender' => 'male',
        'name' => 'Upi Member',
        'email' => 'upi-'.uniqid().'@example.test',
        'mobile' => '98'.random_int(10000000, 99999999),
        'membership_plan_id' => MembershipPlan::where('code', 'F')->value('id'),
        'payment_mode' => 'upi',
        'upi_reference' => 'UTR'.random_int(100000000, 999999999),
        'upi_screenshot' => UploadedFile::fake()->image('paid.png'),
    ], $overrides);
}

beforeEach(function () {
    $this->seed();
    Storage::fake('public');
    upiSponsor();
});

test('Online is not offered while switched off, and a registration choosing it is refused', function () {
    $this->get('/join')->assertInertia(fn ($page) => $page->where('payment_options.modes', ['cash', 'upi']));

    $this->post('/join', upiJoinPayload(['payment_mode' => 'online']))->assertSessionHasErrors('payment_mode');
});

test('GPay/UPI needs both the Ref ID and the screenshot', function () {
    $this->post('/join', upiJoinPayload(['upi_reference' => '', 'upi_screenshot' => null]))
        ->assertSessionHasErrors(['upi_reference', 'upi_screenshot']);
});

test('a GPay/UPI registration waits for approval with its proof, and an Admin can approve it', function () {
    $this->post('/join', upiJoinPayload(['upi_reference' => 'UTR123456789']))->assertRedirect();

    $payment = Payment::where('upi_reference', 'UTR123456789')->sole();
    expect($payment->mode)->toBe('upi')
        ->and($payment->status)->toBe('pending')
        ->and($payment->cash_status)->toBe('pending_verification')
        ->and($payment->upi_screenshot_path)->not->toBeNull();
    Storage::disk('public')->assertExists($payment->upi_screenshot_path);

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get('/super-admin/cash-payments')
        ->assertInertia(fn ($page) => $page
            ->where('pending.0.mode', 'upi')
            ->where('pending.0.upi_reference', 'UTR123456789'));

    $this->actingAs($admin)->post("/super-admin/cash-payments/{$payment->id}/approve")->assertRedirect();

    expect($payment->fresh()->status)->toBe('paid')
        ->and($payment->member->fresh()->status)->toBe('active');
});

test('the same Ref ID can never be used twice', function () {
    $this->post('/join', upiJoinPayload(['upi_reference' => 'UTRDUPLICATE1']))->assertRedirect();
    $this->post('/join', upiJoinPayload(['upi_reference' => 'UTRDUPLICATE1']))->assertSessionHasErrors('upi_reference');

    expect(Payment::where('upi_reference', 'UTRDUPLICATE1')->count())->toBe(1);
});

test('only Super Admin sets the company UPI ID and QR, which then show on the payment step', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->post('/super-admin/payment-settings', ['upi_id' => 'goldwave@okaxis'])
        ->assertForbidden();

    $this->actingAs(User::factory()->create(['role' => 'super_admin']))
        ->post('/super-admin/payment-settings', [
            'upi_id' => 'goldwave@okaxis',
            'upi_qr' => UploadedFile::fake()->image('qr.png'),
        ])
        ->assertRedirect('/super-admin/payment-settings');

    expect(app(RuleVersionService::class)->value('company_upi_id'))->toBe('goldwave@okaxis');

    $this->get('/join')->assertInertia(fn ($page) => $page
        ->where('payment_options.upi_id', 'goldwave@okaxis')
        ->where('payment_options.upi_qr_url', fn ($url) => is_string($url) && $url !== ''));
});

<?php

use App\Events\PaymentConfirmed;
use App\Jobs\ProcessCashPaymentApproval;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Services\RuleVersionService;
use App\Support\PaymentInProgress;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
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
    Storage::fake('local');
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

test('T-219: a Cash registration ignores a leftover GPay/UPI Ref ID and screenshot', function () {
    $this->post('/join', upiJoinPayload([
        'payment_mode' => 'cash',
        'email' => 'cash-left-over@example.test',
        'upi_reference' => '123',
        'upi_screenshot' => UploadedFile::fake()->create('notes.txt'),
    ]))->assertSessionHasNoErrors()->assertRedirect();

    $payment = Member::whereHas('user', fn ($q) => $q->where('email', 'cash-left-over@example.test'))
        ->sole()->payments()->sole();
    expect($payment->mode)->toBe('cash')
        ->and($payment->upi_reference)->toBeNull()
        ->and($payment->upi_screenshot_path)->toBeNull();
});

test('a GPay/UPI registration waits for approval with its proof, and an Admin can approve it', function () {
    $this->post('/join', upiJoinPayload(['upi_reference' => 'UTR123456789']))->assertRedirect();

    $payment = Payment::where('upi_reference', 'UTR123456789')->sole();
    expect($payment->mode)->toBe('upi')
        ->and($payment->status)->toBe('pending')
        ->and($payment->cash_status)->toBe('pending_verification')
        ->and($payment->upi_screenshot_path)->not->toBeNull();
    // T-206 — the screenshot is private and opens only through the company portal's route.
    Storage::disk('local')->assertExists($payment->upi_screenshot_path);
    Storage::disk('public')->assertMissing($payment->upi_screenshot_path);
    $this->get("/super-admin/payments/{$payment->id}/screenshot")->assertRedirect();

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get('/super-admin/cash-payments')
        ->assertInertia(fn ($page) => $page
            ->where('pending.0.mode', 'upi')
            ->where('pending.0.upi_reference', 'UTR123456789')
            ->where('pending.0.upi_screenshot_url', route('super-admin.payments.screenshot', $payment)));
    $this->actingAs($admin)->get("/super-admin/payments/{$payment->id}/screenshot")->assertOk();
    $this->actingAs(User::factory()->create(['role' => 'member']))
        ->get("/super-admin/payments/{$payment->id}/screenshot")->assertForbidden();

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

test('a fresh install already shows the company GPay/UPI QR and UPI ID on the payment step (T-212)', function () {
    $this->get('/join')->assertInertia(fn ($page) => $page
        ->where('payment_options.upi_id', '8814900944@okbizaxis')
        ->where('payment_options.upi_qr_url', fn ($url) => str_ends_with((string) $url, '/Images/QRcode.webp')));

    expect(file_exists(public_path('Images/QRcode.webp')))->toBeTrue();
});

test('a pending GPay/UPI payment alerts the company and shows in the Super Admin dashboard alerts (T-212)', function () {
    $this->post('/join', upiJoinPayload(['upi_reference' => 'UTR555666777']))->assertRedirect();

    $superAdmin = User::where('role', 'super_admin')->firstOrFail();
    expect($superAdmin->notifications()->first()?->data['title'] ?? null)->toBe('GPay/UPI payment awaiting approval');

    $this->actingAs($superAdmin)->get('/dashboard')->assertInertia(fn ($page) => $page
        ->component('super-admin/dashboard')
        ->where('alerts', fn ($alerts) => collect($alerts)->contains('1 cash / GPay-UPI payment(s) awaiting approval.')));
});

test('T-226: the History tab lists approved and rejected payments with who decided them, and filters them', function () {
    $this->post('/join', upiJoinPayload(['name' => 'Ravi Upi', 'upi_reference' => 'UTR111222333']));
    $this->post('/join', upiJoinPayload(['name' => 'Sita Cash', 'payment_mode' => 'cash', 'upi_reference' => null, 'upi_screenshot' => null]));
    $this->post('/join', upiJoinPayload(['name' => 'Still Pending', 'payment_mode' => 'cash', 'upi_reference' => null, 'upi_screenshot' => null]));

    $admin = User::factory()->create(['role' => 'admin', 'name' => 'Desk Admin']);
    $upi = Payment::where('upi_reference', 'UTR111222333')->sole();
    $cash = Payment::whereHas('member.user', fn ($q) => $q->where('name', 'Sita Cash'))->sole();
    $this->actingAs($admin)->post("/super-admin/cash-payments/{$upi->id}/approve");
    $this->actingAs($admin)->post("/super-admin/cash-payments/{$cash->id}/reject");

    // Pending tab: History is not loaded, but its total shows on the tab.
    $this->actingAs($admin)->get('/super-admin/cash-payments')
        ->assertInertia(fn ($page) => $page->where('tab', 'pending')->where('history', null)->where('history_total', 2)->has('pending', 1));

    $this->actingAs($admin)->get('/super-admin/cash-payments?tab=history')
        ->assertInertia(fn ($page) => $page
            ->where('tab', 'history')
            ->has('history.data', 2)
            ->where('history.data', fn ($rows) => collect($rows)->pluck('decided_by')->unique()->all() === ['Desk Admin'])
            ->where('history.data', fn ($rows) => collect($rows)->firstWhere('name', 'Ravi Upi')['cash_status'] === 'approved'
                && collect($rows)->firstWhere('name', 'Sita Cash')['cash_status'] === 'rejected'));

    // Ref ID search (any case), and the status / mode filters.
    $this->actingAs($admin)->get('/super-admin/cash-payments?tab=history&search=utr111')
        ->assertInertia(fn ($page) => $page->has('history.data', 1)->where('history.data.0.name', 'Ravi Upi'));
    $this->actingAs($admin)->get('/super-admin/cash-payments?tab=history&status=rejected')
        ->assertInertia(fn ($page) => $page->has('history.data', 1)->where('history.data.0.name', 'Sita Cash'));
    $this->actingAs($admin)->get('/super-admin/cash-payments?tab=history&mode=upi')
        ->assertInertia(fn ($page) => $page->has('history.data', 1)->where('history.data.0.upi_reference', 'UTR111222333'));
    $this->actingAs($admin)->get('/super-admin/cash-payments?tab=history&from='.now()->addDay()->toDateString())
        ->assertInertia(fn ($page) => $page->has('history.data', 0));
});

test('T-228: approving queues a background approval once, shows it as approving and blocks a rejection meanwhile', function () {
    Queue::fake();
    $this->post('/join', upiJoinPayload(['name' => 'Queued Member', 'upi_reference' => 'UTR555666777']));
    $payment = Payment::where('upi_reference', 'UTR555666777')->sole();
    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)->post("/super-admin/cash-payments/{$payment->id}/approve")->assertSessionHas('status');
    $this->actingAs($admin)->post("/super-admin/cash-payments/{$payment->id}/approve");
    Queue::assertPushed(ProcessCashPaymentApproval::class, 1);

    // Nothing is decided yet; the row says it is being approved, and it can't be rejected now.
    expect($payment->fresh()->cash_status)->toBe('pending_verification');
    $this->actingAs($admin)->get('/super-admin/cash-payments')
        ->assertInertia(fn ($page) => $page->where('pending.0.approving', true));
    $this->actingAs($admin)->post("/super-admin/cash-payments/{$payment->id}/reject");
    expect($payment->fresh()->cash_status)->toBe('pending_verification');
});

test('T-228: a failing background approval keeps nothing — the payment stays pending and the company users are told', function () {
    $this->post('/join', upiJoinPayload(['name' => 'Rollback Member', 'upi_reference' => 'UTR888999000']));
    $payment = Payment::where('upi_reference', 'UTR888999000')->sole();
    $admin = User::factory()->create(['role' => 'super_admin']);
    PaymentInProgress::claim($payment->id);

    // An income listener fails half-way through the approval.
    Event::listen(PaymentConfirmed::class, fn () => throw new RuntimeException('income failed'));

    expect(fn () => ProcessCashPaymentApproval::dispatchSync($payment, $admin))->toThrow(RuntimeException::class);

    $payment->refresh();
    expect($payment->cash_status)->toBe('pending_verification')
        ->and($payment->status)->toBe('pending')
        ->and($payment->member->fresh()->status)->not->toBe('active')
        ->and(PaymentInProgress::isRunning($payment->id))->toBeFalse()
        ->and($admin->notifications()->where('data', 'like', '%Payment approval failed%')->exists())->toBeTrue();
});

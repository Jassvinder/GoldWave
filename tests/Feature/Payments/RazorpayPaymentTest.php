<?php

use App\Contracts\PaymentGatewayContract;
use App\Events\PaymentConfirmed;
use App\Models\EmiInstallment;
use App\Models\EmiSchedule;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\RazorpayGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * T-137 — Docs/TEST.md scenario 23. Razorpay's network is always faked; real Razorpay is never called.
 */
const RZP_SECRET = 'test_key_secret_value';
const RZP_WEBHOOK_SECRET = 'test_webhook_secret_value';

function rzpConfigure(): void
{
    config([
        'services.payments.gateway' => 'razorpay',
        'services.razorpay.key_id' => 'rzp_test_KEYID',
        'services.razorpay.key_secret' => RZP_SECRET,
        'services.razorpay.webhook_secret' => RZP_WEBHOOK_SECRET,
    ]);
}

/**
 * @param  array<string, mixed>  $payment  fields of Razorpay's payment record (merged over a captured ₹1,000 payment)
 */
function rzpFake(array $payment = [], string $orderId = 'order_TEST1'): void
{
    // A fresh factory each time: stubs registered earlier would otherwise win over these ones.
    Http::swap(new HttpFactory);
    Http::preventStrayRequests();
    Http::fake([
        'api.razorpay.com/v1/orders' => Http::response(['id' => $orderId, 'entity' => 'order', 'status' => 'created', 'amount' => 100000, 'currency' => 'INR']),
        'api.razorpay.com/v1/payments/pay_TEST1/capture' => Http::response(['id' => 'pay_TEST1', 'status' => 'captured']),
        'api.razorpay.com/v1/payments/pay_TEST1' => Http::response(array_merge([
            'id' => 'pay_TEST1', 'entity' => 'payment', 'order_id' => $orderId, 'amount' => 100000, 'currency' => 'INR', 'status' => 'captured', 'method' => 'upi',
        ], $payment)),
    ]);
}

function rzpSignature(string $orderId = 'order_TEST1', string $paymentId = 'pay_TEST1', string $secret = RZP_SECRET): string
{
    return hash_hmac('sha256', $orderId.'|'.$paymentId, $secret);
}

/** A real online registration through /join (Plan A, ₹1,000 Future Rate); returns the pending payment. */
function rzpPendingRegistration(string $email = 'rzp@example.test', string $mobile = '9876543001'): Payment
{
    $sponsorUser = User::factory()->create(['role' => 'member']);
    Member::firstOrCreate(['customer_id' => 'GWL900'], [
        'user_id' => $sponsorUser->id, 'gender' => 'male', 'status' => 'active', 'activated_at' => now(),
    ]);

    test()->post('/join', [
        'sponsor_code' => 'GWL900',
        'placement_side' => 'left',
        'gender' => 'female',
        'name' => 'Razorpay Member',
        'email' => $email,
        'mobile' => $mobile,
        'membership_plan_id' => MembershipPlan::where('code', 'A')->value('id'),
        'payment_mode' => 'online',
    ])->assertRedirect();

    $member = Member::whereHas('user', fn ($q) => $q->where('email', $email))->firstOrFail();

    return Payment::where('member_id', $member->id)->where('type', 'registration')->firstOrFail();
}

/** @return array<string, string> */
function rzpReturn(string $signature = '', string $orderId = 'order_TEST1', string $paymentId = 'pay_TEST1'): array
{
    return [
        'razorpay_payment_id' => $paymentId,
        'razorpay_order_id' => $orderId,
        'razorpay_signature' => $signature !== '' ? $signature : rzpSignature($orderId, $paymentId),
    ];
}

/** @param array<string, mixed> $entity */
function rzpWebhook(string $event = 'order.paid', array $entity = [], ?string $secret = RZP_WEBHOOK_SECRET, ?string $tamper = null)
{
    $body = json_encode([
        'entity' => 'event',
        'event' => $event,
        'contains' => ['payment'],
        'payload' => ['payment' => ['entity' => array_merge([
            'id' => 'pay_TEST1', 'order_id' => 'order_TEST1', 'amount' => 100000, 'currency' => 'INR', 'status' => 'captured', 'method' => 'upi',
        ], $entity)]],
    ], JSON_UNESCAPED_SLASHES);

    $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

    if ($secret !== null) {
        $server['HTTP_X_RAZORPAY_SIGNATURE'] = hash_hmac('sha256', $body, $secret);
    }

    return test()->call('POST', '/payments/webhook', [], [], [], $server, $tamper ?? $body);
}

beforeEach(function () {
    $this->seed();
    // T-196 — Online (Razorpay) is off by default; these tests keep the switched-off code path working.
    config(['services.payments.online_enabled' => true]);
    rzpConfigure();
    rzpFake();
});

test('creating the intent sends one order in paise with basic auth, saves the order id, returns a signed checkout link and reuses the order', function () {
    $payment = rzpPendingRegistration();

    expect($payment->fresh()->gateway_order_id)->toBe('order_TEST1');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.razorpay.com/v1/orders'
        && $request->method() === 'POST'
        && $request['amount'] === 100000
        && $request['currency'] === 'INR'
        && $request['receipt'] === 'GW-'.$payment->id
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('rzp_test_KEYID:'.RZP_SECRET)));

    $intent = app(PaymentGatewayContract::class)->createIntent($payment->fresh());

    expect($intent['reference'])->toBe('order_TEST1');
    expect($intent['redirect_url'])->toContain("/payments/{$payment->id}/checkout")->toContain('signature=');
    Http::assertSentCount(1); // the second call reused the order.
});

test('the checkout page needs the signed link and hands Checkout the key and order', function () {
    $payment = rzpPendingRegistration();

    $this->get("/payments/{$payment->id}/checkout")->assertForbidden();

    $this->get(RazorpayGateway::checkoutUrl($payment))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('payments/razorpay-checkout')
            ->where('razorpay.key', 'rzp_test_KEYID')
            ->where('razorpay.order_id', 'order_TEST1')
            ->where('razorpay.amount', 100000)
            ->where('razorpay.currency', 'INR')
            ->where('prefill.email', 'rzp@example.test')
            ->missing('razorpay.key_secret'));
});

test('a verified, captured payment confirms the registration once and activates the membership', function () {
    Event::fake([PaymentConfirmed::class]);
    $payment = rzpPendingRegistration();
    $member = $payment->member;

    $this->post("/payments/{$payment->id}/razorpay/verify", rzpReturn())
        ->assertRedirectContains('/join/status/');

    $payment = $payment->fresh();
    expect($payment->status)->toBe('paid');
    expect($payment->provider_reference)->toBe('pay_TEST1');
    expect($member->fresh()->status)->toBe('active');
    expect($member->fresh()->customer_id)->not->toBeNull();

    // A second verification (browser retry) changes nothing.
    $customerId = $member->fresh()->customer_id;
    $this->post("/payments/{$payment->id}/razorpay/verify", rzpReturn())->assertRedirectContains('/join/status/');

    expect($member->fresh()->customer_id)->toBe($customerId);
    Event::assertDispatchedTimes(PaymentConfirmed::class, 1);
});

test('an authorized payment is captured through the API before it counts', function () {
    $payment = rzpPendingRegistration();
    rzpFake(['status' => 'authorized']);

    $this->post("/payments/{$payment->id}/razorpay/verify", rzpReturn())->assertRedirectContains('/join/status/');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.razorpay.com/v1/payments/pay_TEST1/capture'
        && $request['amount'] === 100000 && $request['currency'] === 'INR');
    expect($payment->fresh()->status)->toBe('paid');
});

test('anything Razorpay does not confirm as a captured payment for our order leaves the payment pending', function (string $signature, string $orderId, array $razorpayPayment) {
    $payment = rzpPendingRegistration();
    rzpFake($razorpayPayment);

    $return = $orderId === 'order_TEST1' && $signature === ''
        ? rzpReturn()
        : rzpReturn($signature !== '' ? $signature : rzpSignature($orderId), $orderId);

    $this->post("/payments/{$payment->id}/razorpay/verify", $return)->assertSessionHasErrors('payment');

    expect($payment->fresh()->status)->toBe('pending');
    expect($payment->member->fresh()->status)->not->toBe('active');
})->with([
    'wrong signature' => ['deadbeef', 'order_TEST1', []],
    'signature valid for another order' => ['', 'order_OTHER', []],
    'different amount at Razorpay' => ['', 'order_TEST1', ['amount' => 100]],
    'different currency at Razorpay' => ['', 'order_TEST1', ['currency' => 'USD']],
    'Razorpay says failed' => ['', 'order_TEST1', ['status' => 'failed']],
    'Razorpay says created' => ['', 'order_TEST1', ['status' => 'created']],
    'payment belongs to another order at Razorpay' => ['', 'order_TEST1', ['order_id' => 'order_OTHER']],
]);

test('when Razorpay cannot be reached the payment stays pending with a reassuring message', function () {
    $payment = rzpPendingRegistration();
    Http::swap(new HttpFactory);
    Http::preventStrayRequests();
    Http::fake(['api.razorpay.com/*' => fn () => throw new ConnectionException('timeout')]);

    $this->post("/payments/{$payment->id}/razorpay/verify", rzpReturn())->assertSessionHasErrors('payment');

    expect(session('errors')->first('payment'))->toContain('confirmed automatically');

    expect($payment->fresh()->status)->toBe('pending');
});

test('a valid webhook confirms the payment once; replays and a later return verification do nothing more', function () {
    Event::fake([PaymentConfirmed::class]);
    $payment = rzpPendingRegistration();

    rzpWebhook()->assertOk()->assertJson(['status' => 'ok']);

    expect($payment->fresh()->status)->toBe('paid');
    expect($payment->member->fresh()->status)->toBe('active');
    $customerId = $payment->member->fresh()->customer_id;

    rzpWebhook('payment.captured')->assertOk();
    $this->post("/payments/{$payment->id}/razorpay/verify", rzpReturn())->assertRedirectContains('/join/status/');

    expect($payment->member->fresh()->customer_id)->toBe($customerId);
    Event::assertDispatchedTimes(PaymentConfirmed::class, 1);
});

test('a webhook with a tampered body, a wrong secret or no signature is rejected and confirms nothing', function () {
    $payment = rzpPendingRegistration();

    rzpWebhook(tamper: '{"event":"order.paid","payload":{"payment":{"entity":{"id":"pay_TEST1","order_id":"order_TEST1","amount":100000,"currency":"INR","status":"captured"}}}}')->assertStatus(400);
    rzpWebhook(secret: 'not-the-secret')->assertStatus(400);
    rzpWebhook(secret: null)->assertStatus(400);

    expect($payment->fresh()->status)->toBe('pending');
});

test('webhook events that need no action, unknown orders and mismatching amounts are acknowledged but change nothing', function () {
    $payment = rzpPendingRegistration();

    rzpWebhook('payment.failed', ['status' => 'failed'])->assertOk()->assertJson(['status' => 'ignored']);
    rzpWebhook('order.paid', ['order_id' => 'order_NOT_OURS'])->assertOk()->assertJson(['status' => 'ignored']);
    rzpWebhook('order.paid', ['amount' => 500])->assertOk()->assertJson(['status' => 'ignored']);
    rzpWebhook('order.paid', ['status' => 'authorized'])->assertOk()->assertJson(['status' => 'ignored']);

    expect($payment->fresh()->status)->toBe('pending');
});

test('an online EMI installment goes through the same gateway and confirms the installment', function () {
    $plan = MembershipPlan::where('code', 'A')->firstOrFail();
    $user = User::factory()->create(['role' => 'member']);
    $member = Member::create(['user_id' => $user->id, 'customer_id' => 'GWL777', 'gender' => 'male', 'membership_plan_id' => $plan->id, 'status' => 'active', 'activated_at' => now()]);
    $schedule = EmiSchedule::create([
        'member_id' => $member->id, 'membership_plan_id' => $plan->id, 'total_installments' => 20,
        'rate_booking_method' => 'future_rate', 'installment_amount' => 1000,
    ]);
    EmiInstallment::create(['emi_schedule_id' => $schedule->id, 'installment_no' => 1, 'due_date' => now()->subMonth()->toDateString(), 'amount' => 1000, 'status' => 'paid']);
    $second = EmiInstallment::create(['emi_schedule_id' => $schedule->id, 'installment_no' => 2, 'due_date' => now()->toDateString(), 'amount' => 1000, 'status' => 'due']);

    $response = $this->actingAs($user)->post("/member/emi/{$second->id}/pay", ['mode' => 'online']);

    $payment = Payment::findOrFail($second->fresh()->payment_id);
    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain("/payments/{$payment->id}/checkout");
    expect($payment->gateway_order_id)->toBe('order_TEST1');

    $this->actingAs($user)->post("/payments/{$payment->id}/razorpay/verify", rzpReturn())->assertRedirect(route('member.emi.index'));

    expect($payment->fresh()->status)->toBe('paid');
    expect($second->fresh()->status)->toBe('paid');
});

test('the gateway is Razorpay once both keys are set, the fake one otherwise, and never the fake one in production', function () {
    config(['services.payments.gateway' => null]);
    expect(app(PaymentGatewayContract::class))->toBeInstanceOf(RazorpayGateway::class);

    config(['services.razorpay.key_secret' => '']);
    expect(app(PaymentGatewayContract::class))->toBeInstanceOf(FakePaymentGateway::class);

    app()['env'] = 'production';

    try {
        expect(fn () => app(PaymentGatewayContract::class))->toThrow(RuntimeException::class);
    } finally {
        app()['env'] = 'testing';
    }
});

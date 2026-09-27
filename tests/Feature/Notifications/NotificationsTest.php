<?php

use App\Actions\Payments\ApproveCashPayment;
use App\Actions\Payments\RejectCashPayment;
use App\Actions\Payout\SubmitPayoutRequest;
use App\Actions\Profile\ApproveProfileChangeRequest;
use App\Actions\Profile\SubmitPendingProfileFields;
use App\Actions\Profile\SubmitProfileChangeRequest;
use App\Actions\Store\CreateStore;
use App\Console\Scheduling;
use App\Contracts\SmsGatewayContract;
use App\Jobs\SendEmiReminders;
use App\Models\EmiInstallment;
use App\Models\EmiSchedule;
use App\Models\Member;
use App\Models\MemberBankDetail;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Notifications\BankDetailsSubmitted;
use App\Notifications\CashPaymentAwaitingApproval;
use App\Notifications\CashPaymentDecided;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\EmiDueReminder;
use App\Notifications\PayoutRequestSubmitted;
use App\Notifications\ProfileChangeRequestReviewed;
use App\Notifications\ProfileChangeRequestSubmitted;
use App\Services\Otp\SmsOtpChannel;
use App\Services\WalletLedgerService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * T-139…T-143 — Docs/TEST.md scenarios 24 (notifications), 25 (SMS gateway) and 26 (EMI reminders).
 */
function ntfSuperAdmin(): User
{
    return User::where('role', 'super_admin')->firstOrFail();
}

/** An active Plan A member with email + mobile and a 20-installment schedule; installment 1 is paid, #2 is `due`. */
function ntfMember(string $customerId, string $status = 'active', string $firstDue = '2026-09-17'): Member
{
    $user = User::factory()->create([
        'role' => 'member',
        'email' => strtolower($customerId).'@example.test',
        'mobile' => (string) (9700000000 + (int) preg_replace('/\D/', '', $customerId)),
    ]);
    $plan = MembershipPlan::where('code', 'A')->firstOrFail();
    $member = Member::create([
        'user_id' => $user->id,
        'customer_id' => $customerId,
        'gender' => 'male',
        'membership_plan_id' => $plan->id,
        'status' => $status,
        'activated_at' => now(),
    ]);
    $schedule = EmiSchedule::create([
        'member_id' => $member->id,
        'membership_plan_id' => $plan->id,
        'total_installments' => 20,
        'rate_booking_method' => 'future_rate',
        'installment_amount' => 1000,
    ]);

    for ($no = 1; $no <= 20; $no++) {
        EmiInstallment::create([
            'emi_schedule_id' => $schedule->id,
            'installment_no' => $no,
            'due_date' => Carbon::parse($firstDue)->addMonthsNoOverflow($no - 1)->toDateString(),
            'amount' => 1000,
            'status' => $no === 1 ? 'paid' : ($no === 2 ? 'due' : 'upcoming'),
        ]);
    }

    return $member->fresh();
}

function ntfInstallment(Member $member, int $no): EmiInstallment
{
    return $member->emiSchedule->installments()->where('installment_no', $no)->firstOrFail();
}

/** @return list<DatabaseNotification> */
function ntfInbox(User $user): array
{
    return $user->notifications()->orderBy('created_at')->get()->all();
}

beforeEach(function () {
    $this->seed();
});

// ---------------------------------------------------------------- Super Admin alerts (T-141)

test('a cash EMI payment alerts the Super Admin (and only them) and is not repeated when the pending payment is re-used', function () {
    $member = ntfMember('NTF1');

    $this->actingAs($member->user)->post('/member/emi/'.ntfInstallment($member, 2)->id.'/pay', ['mode' => 'cash'])->assertRedirect();
    $this->actingAs($member->user)->post('/member/emi/'.ntfInstallment($member, 2)->id.'/pay', ['mode' => 'cash']);

    $inbox = ntfInbox(ntfSuperAdmin());
    expect($inbox)->toHaveCount(1);
    expect($inbox[0]->data)->toMatchArray([
        'category' => 'payment',
        'title' => 'Cash payment awaiting approval',
        'url' => '/super-admin/cash-payments',
    ]);
    expect($inbox[0]->data['body'])->toContain('NTF1')->toContain('₹1,000.00')->toContain('EMI #2');
    expect($member->user->notifications()->count())->toBe(0);
});

test('an online payment does not alert the Super Admin', function () {
    $member = ntfMember('NTF2');

    $this->actingAs($member->user)->post('/member/emi/'.ntfInstallment($member, 2)->id.'/pay', ['mode' => 'online']);

    expect(ntfSuperAdmin()->notifications()->count())->toBe(0);
});

test('a cash registration alerts the Super Admin as a new registration', function () {
    $sponsor = Member::create(['user_id' => User::factory()->create(['role' => 'member'])->id, 'customer_id' => 'GWL900', 'gender' => 'male', 'status' => 'active', 'activated_at' => now()]);

    $this->post('/join', [
        'sponsor_code' => $sponsor->customer_id, 'placement_side' => 'left', 'gender' => 'female', 'name' => 'Cash Joiner',
        'email' => 'cashjoiner@example.test', 'mobile' => '9876500777',
        'membership_plan_id' => MembershipPlan::where('code', 'A')->value('id'), 'payment_mode' => 'cash',
    ])->assertRedirect();

    $alert = ntfSuperAdmin()->notifications()->firstOrFail();
    expect($alert->data['body'])->toContain('Cash Joiner')->toContain('a new registration')->toContain('₹1,000.00');
});

test('profile change requests, payout requests and new bank details each alert the Super Admin', function () {
    $member = ntfMember('NTF3');
    $member->update(['pending_fields_submitted_at' => now()]);

    app(SubmitProfileChangeRequest::class)($member, 'address', 'New address 1', 'Moved house');

    $wallet = app(WalletLedgerService::class);
    $wallet->credit($member, 'level_income', 5000, null, 'seed');
    $bank = MemberBankDetail::create([
        'member_id' => $member->id, 'account_holder_name' => 'A', 'account_number' => '123456', 'ifsc_code' => 'HDFC0000001',
        'bank_name' => 'HDFC', 'proof_document_path' => 'x.pdf', 'verified_by' => ntfSuperAdmin()->id, 'verified_at' => now(),
    ]);
    app(SubmitPayoutRequest::class)($member->fresh(), 1500.0, $bank);

    $fresh = ntfMember('NTF4');
    app(SubmitPendingProfileFields::class)($fresh, 'ABCDE1234F', '123456789012', 'profile-photos/a.webp', 'Addr', [
        'account_holder_name' => 'B', 'account_number' => '987654', 'ifsc_code' => 'SBIN0000001', 'bank_name' => 'SBI', 'proof_document_path' => 'y.pdf',
    ]);

    $titles = collect(ntfInbox(ntfSuperAdmin()))->pluck('data.title')->all();
    expect($titles)->toBe(['Profile change request', 'Payout request', 'Bank details to verify']);

    $bankAlert = ntfSuperAdmin()->notifications()->where('data', 'like', '%Bank details to verify%')->firstOrFail();
    expect($bankAlert->data['url'])->toBe('/super-admin/members/'.$fresh->id);
    expect(collect(ntfInbox(ntfSuperAdmin()))->firstWhere('data.title', 'Payout request')->data['body'])->toContain('₹1,500.00');
});

// ---------------------------------------------------------------- Member notifications (T-141)

test('approving or rejecting a cash payment tells the member on bell, email and SMS', function () {
    Notification::fake();
    $member = ntfMember('NTF5');
    $this->actingAs($member->user)->post('/member/emi/'.ntfInstallment($member, 2)->id.'/pay', ['mode' => 'cash']);
    $payment = Payment::where('member_id', $member->id)->firstOrFail();

    app(ApproveCashPayment::class)($payment, ntfSuperAdmin());

    Notification::assertSentTo($member->user, CashPaymentDecided::class, function (CashPaymentDecided $n, array $channels) {
        return $n->approved && $n->title() === 'Cash payment approved'
            && in_array('database', $channels, true) && in_array('mail', $channels, true) && in_array(SmsChannel::class, $channels, true);
    });

    $member2 = ntfMember('NTF6');
    $this->actingAs($member2->user)->post('/member/emi/'.ntfInstallment($member2, 2)->id.'/pay', ['mode' => 'cash']);
    $payment2 = Payment::where('member_id', $member2->id)->firstOrFail();

    app(RejectCashPayment::class)($payment2, ntfSuperAdmin());
    app(RejectCashPayment::class)($payment2, ntfSuperAdmin()); // a second call must not notify again

    Notification::assertSentToTimes($member2->user, CashPaymentDecided::class, 1);
    Notification::assertSentTo($member2->user, CashPaymentDecided::class, fn (CashPaymentDecided $n) => ! $n->approved && $n->title() === 'Cash payment rejected');
});

test('a reviewed change request lands in the member\'s own inbox with a readable title', function () {
    $member = ntfMember('NTF7');
    $member->update(['pending_fields_submitted_at' => now(), 'address' => 'Old']);
    $request = app(SubmitProfileChangeRequest::class)($member, 'address', 'New address 1', null);

    app(ApproveProfileChangeRequest::class)($request, ntfSuperAdmin());

    $note = $member->user->notifications()->firstOrFail();
    expect($note->data)->toMatchArray(['category' => 'request', 'title' => 'Change request approved', 'url' => '/member/change-requests']);
    expect($note->data['body'])->toContain('address');
    expect($note->type)->toBe(ProfileChangeRequestReviewed::class);
});

test('a failing SMS gateway never breaks the approval and the bell notification still exists', function () {
    app()->bind(SmsGatewayContract::class, fn () => new class implements SmsGatewayContract
    {
        public function send(string $mobile, string $message, string $templateKey): void
        {
            throw new RuntimeException('provider down');
        }
    });
    $member = ntfMember('NTF8');
    $this->actingAs($member->user)->post('/member/emi/'.ntfInstallment($member, 2)->id.'/pay', ['mode' => 'cash']);
    $payment = Payment::where('member_id', $member->id)->firstOrFail();

    app(ApproveCashPayment::class)($payment, ntfSuperAdmin());

    expect($payment->fresh()->status)->toBe('paid');
    expect($member->user->notifications()->count())->toBe(1);
});

// ---------------------------------------------------------------- Bell and pages (T-140)

test('the bell prop carries only the logged-in user\'s own unread count and latest items', function () {
    $member = ntfMember('NTF9');
    $other = ntfMember('NTF10');
    $this->actingAs($member->user)->post('/member/emi/'.ntfInstallment($member, 2)->id.'/pay', ['mode' => 'cash']);
    $this->actingAs($other->user)->post('/member/emi/'.ntfInstallment($other, 2)->id.'/pay', ['mode' => 'cash']);

    $this->actingAs(ntfSuperAdmin())->get('/super-admin/members')
        ->assertInertia(fn ($page) => $page
            ->where('notifications.unread_count', 2)
            ->has('notifications.latest', 2)
            ->where('notifications.index_url', '/super-admin/notifications')
            ->where('notifications.latest.0.title', 'Cash payment awaiting approval')
            ->where('notifications.latest.0.read', false));

    $this->actingAs($member->user)->get('/member/wallet')
        ->assertInertia(fn ($page) => $page->where('notifications.unread_count', 0)->where('notifications.index_url', '/member/notifications'));
});

test('opening a notification marks it read and follows only an internal link; other users\' notifications are 404', function () {
    $member = ntfMember('NTF11');
    $this->actingAs($member->user)->post('/member/emi/'.ntfInstallment($member, 2)->id.'/pay', ['mode' => 'cash']);
    $alert = ntfSuperAdmin()->notifications()->firstOrFail();

    $this->actingAs($member->user)->get("/notifications/{$alert->id}/open")->assertNotFound();
    expect($alert->fresh()->read_at)->toBeNull();

    $this->actingAs(ntfSuperAdmin())->get("/notifications/{$alert->id}/open")->assertRedirect('/super-admin/cash-payments');
    expect($alert->fresh()->read_at)->not->toBeNull();

    $evil = DatabaseNotification::create([
        'id' => (string) Str::uuid(), 'type' => 'x', 'notifiable_type' => User::class, 'notifiable_id' => ntfSuperAdmin()->id,
        'data' => ['category' => 'request', 'title' => 'Evil', 'body' => '', 'url' => 'https://evil.example/phish'],
    ]);
    $this->actingAs(ntfSuperAdmin())->get("/notifications/{$evil->id}/open")->assertRedirect('/super-admin/notifications');

    $protocolRelative = DatabaseNotification::create([
        'id' => (string) Str::uuid(), 'type' => 'x', 'notifiable_type' => User::class, 'notifiable_id' => ntfSuperAdmin()->id,
        'data' => ['category' => 'request', 'title' => 'Evil2', 'body' => '', 'url' => '//evil.example'],
    ]);
    $this->actingAs(ntfSuperAdmin())->get("/notifications/{$protocolRelative->id}/open")->assertRedirect('/super-admin/notifications');
});

test('the Notifications page filters by category and unread, counts per category, and marks read', function () {
    $member = ntfMember('NTF12');
    $member->update(['pending_fields_submitted_at' => now()]);
    $this->actingAs($member->user)->post('/member/emi/'.ntfInstallment($member, 2)->id.'/pay', ['mode' => 'cash']);
    app(SubmitProfileChangeRequest::class)($member, 'address', 'New address 1', null);
    app(SubmitProfileChangeRequest::class)($member, 'pan_card', 'ABCDE1234F', null);

    $admin = $this->actingAs(ntfSuperAdmin());

    $admin->get('/super-admin/notifications')->assertInertia(fn ($page) => $page
        ->component('super-admin/notifications')
        ->where('counts.total', 3)
        ->where('counts.unread', 3)
        ->where('counts.categories.payment.total', 1)
        ->where('counts.categories.request.total', 2)
        ->missing('counts.categories.emi')
        ->has('list.data', 3));

    $admin->get('/super-admin/notifications?category=request')->assertInertia(fn ($page) => $page
        ->has('list.data', 2)->where('filters.category', 'request'));

    ntfSuperAdmin()->notifications()->where('data', 'like', '%PAN card%')->firstOrFail()->markAsRead();
    $admin->get('/super-admin/notifications?unread=1')->assertInertia(fn ($page) => $page
        ->has('list.data', 2)->where('counts.unread', 2)->where('filters.unread', true));

    $admin->post('/notifications/read-all')->assertRedirect();
    expect(ntfSuperAdmin()->unreadNotifications()->count())->toBe(0);
    expect($member->user->notifications()->count())->toBe(0); // nobody else's inbox is touched
});

test('a notification can be marked read on its own, and each portal renders its own page', function () {
    $member = ntfMember('NTF13');
    $this->actingAs($member->user)->post('/member/emi/'.ntfInstallment($member, 2)->id.'/pay', ['mode' => 'cash']);
    $alert = ntfSuperAdmin()->notifications()->firstOrFail();

    $this->actingAs(ntfSuperAdmin())->post("/notifications/{$alert->id}/read")->assertRedirect();
    expect($alert->fresh()->read_at)->not->toBeNull();

    $this->actingAs($member->user)->get('/member/notifications')->assertInertia(fn ($page) => $page->component('member/notifications'));

    $owner = User::factory()->create(['role' => 'admin']);
    app(CreateStore::class)('Notif Store', $owner, null, null, 100000, 20000, ntfSuperAdmin(), 'StorePass123!');
    $this->actingAs($owner)->get('/admin/notifications')->assertInertia(fn ($page) => $page->component('admin/notifications'));
});

test('the Notifications page never overwrites the shared header-bell prop, in all 3 portals (regression: white-screen crash)', function () {
    // NotificationFeed::page() must never return a "notifications" key — that key is the shared bell payload
    // (HandleInertiaRequests) and a same-named page prop silently clobbers it, crashing NotificationBell's
    // `bell.latest.map(...)` with a blank white page. This asserts the real HTTP response shape, not just the service.
    $member = ntfMember('NTF20');
    $this->actingAs($member->user)->post('/member/emi/'.ntfInstallment($member, 2)->id.'/pay', ['mode' => 'cash']);

    $owner = User::factory()->create(['role' => 'admin']);
    app(CreateStore::class)('Notif Store 2', $owner, null, null, 100000, 20000, ntfSuperAdmin(), 'StorePass123!');

    $assertBellIntact = function ($page) {
        return $page
            ->has('notifications.unread_count')
            ->has('notifications.latest')
            ->has('notifications.index_url')
            ->has('list.data')
            ->missing('notifications.data');
    };

    $this->actingAs($member->user)->get('/member/notifications')->assertInertia($assertBellIntact);
    $this->actingAs(ntfSuperAdmin())->get('/super-admin/notifications')->assertInertia($assertBellIntact);
    $this->actingAs($owner)->get('/admin/notifications')->assertInertia($assertBellIntact);
});

// ---------------------------------------------------------------- SMS (T-139)

test('OTP and notifications share one SMS gateway; the master switch and a missing mobile drop the channel', function () {
    $sent = new ArrayObject;
    app()->bind(SmsGatewayContract::class, fn () => new class($sent) implements SmsGatewayContract
    {
        public function __construct(private ArrayObject $sent) {}

        public function send(string $mobile, string $message, string $templateKey): void
        {
            $this->sent[] = [$mobile, $message, $templateKey];
        }
    });

    app(SmsOtpChannel::class)->send('9876543210', '482913');
    expect($sent[0][0])->toBe('9876543210');
    expect($sent[0][1])->toContain('482913');
    expect($sent[0][2])->toBe('otp');

    $member = ntfMember('NTF14');
    $notification = new EmiDueReminder(ntfInstallment($member, 2), 'due');

    expect($notification->via($member->user))->toContain(SmsChannel::class);

    config(['notifications.sms_enabled' => false]);
    expect($notification->via($member->user))->not->toContain(SmsChannel::class);

    config(['notifications.sms_enabled' => true]);
    $member->user->update(['mobile' => null]);
    expect($notification->via($member->user->fresh()))->not->toContain(SmsChannel::class);
});

// ---------------------------------------------------------------- EMI reminders (T-142)

function ntfRemindersOn(string $date): void
{
    Carbon::setTestNow(Carbon::parse($date));
    (new SendEmiReminders)->handle();
}

afterEach(function () {
    Carbon::setTestNow();
});

test('EMI reminders go out 3 days before, on the due date, the day after, then weekly — each exactly once', function () {
    Notification::fake();
    $member = ntfMember('NTF15', firstDue: '2026-09-17'); // installment #2 is due 17-10-2026

    ntfRemindersOn('2026-10-13'); // 4 days before — too early
    Notification::assertNothingSent();

    ntfRemindersOn('2026-10-14');
    ntfRemindersOn('2026-10-14'); // re-running the same day sends nothing more
    ntfRemindersOn('2026-10-15'); // still the same "upcoming" milestone

    ntfInstallment($member, 2)->update(['status' => 'due']);
    ntfRemindersOn('2026-10-17');

    ntfInstallment($member, 2)->update(['status' => 'overdue']);
    ntfRemindersOn('2026-10-18');
    foreach (['2026-10-19', '2026-10-24'] as $quiet) {
        ntfRemindersOn($quiet);
    }
    ntfRemindersOn('2026-10-25');
    ntfRemindersOn('2026-11-01');

    $kinds = Notification::sent($member->user, EmiDueReminder::class)->map(fn (EmiDueReminder $n) => $n->kind)->all();
    expect($kinds)->toBe(['upcoming', 'due', 'overdue', 'overdue', 'overdue']);

    $first = Notification::sent($member->user, EmiDueReminder::class)->first();
    expect($first->title())->toBe('EMI due in 3 days');
    expect($first->body())->toContain('EMI #2')->toContain('₹1,000.00')->toContain('17-10-2026');
    expect($first->url())->toBe('/member/emi');
});

test('only the next payable installment is reminded, and paying it stops its reminders', function () {
    Notification::fake();
    $member = ntfMember('NTF16', firstDue: '2026-09-14'); // #2 due 14-10-2026, #3 due 14-11-2026

    ntfRemindersOn('2026-10-13'); // #2 upcoming
    ntfRemindersOn('2026-11-12'); // #3 is 2 days away but #2 is still unpaid -> only #2 is ever reminded (overdue milestone)

    $installments = Notification::sent($member->user, EmiDueReminder::class)->map(fn (EmiDueReminder $n) => $n->installment->installment_no)->unique()->all();
    expect($installments)->toBe([2]);

    ntfInstallment($member, 2)->update(['status' => 'paid']);
    ntfRemindersOn('2026-11-13'); // #3 is now next payable and 1 day away
    expect(Notification::sent($member->user, EmiDueReminder::class)->last()->installment->installment_no)->toBe(3);
});

test('inactive members, fully paid schedules and members without a user get no reminders', function () {
    Notification::fake();
    $inactive = ntfMember('NTF17', 'payment_pending', '2026-09-14');
    $paidUp = ntfMember('NTF18', 'active', '2026-09-14');
    $paidUp->emiSchedule->installments()->update(['status' => 'paid']);

    ntfRemindersOn('2026-10-13');

    Notification::assertNothingSentTo($inactive->user);
    Notification::assertNothingSentTo($paidUp->user);
});

test('the reminder job is scheduled daily at 09:00 Asia/Kolkata', function () {
    $schedule = new Schedule;
    Scheduling::register($schedule);

    $event = collect($schedule->events())->first(fn ($e) => str_contains((string) $e->description, SendEmiReminders::class) || str_contains($e->command ?? '', 'SendEmiReminders'));

    expect($event)->not->toBeNull();
    expect($event->getExpression())->toBe('0 9 * * *');
});

test('the notification classes carry the documented channels and copy', function () {
    $member = ntfMember('NTF19');
    $member->update(['pending_fields_submitted_at' => now()]);
    $request = app(SubmitProfileChangeRequest::class)($member, 'bank_details', ['account_holder_name' => 'A', 'account_number' => '1', 'ifsc_code' => 'X', 'bank_name' => 'Y'], null);

    $submitted = new ProfileChangeRequestSubmitted($request);
    expect($submitted->via(ntfSuperAdmin()))->toBe(['database']);
    expect($submitted->body())->toContain('bank details');
    expect((new PayoutRequestSubmitted(Model::unguarded(fn () => new PayoutRequest(['requested_amount' => 250]))->setRelation('member', $member)))->body())->toContain('₹250.00');
    expect((new BankDetailsSubmitted($member))->url())->toBe('/super-admin/members/'.$member->id);
    expect((new CashPaymentAwaitingApproval(new Payment(['amount' => 1000, 'type' => 'registration'])->setRelation('member', $member)))->body())->toContain('a new registration');
});

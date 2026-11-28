<?php

use App\Actions\Registration\ActivateMembershipOnPaymentConfirmed;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Services\Otp\EmailOtpChannel;
use App\Services\Otp\SmsOtpChannel;
use App\Support\MemberIds;
use Database\Seeders\MembershipPlanSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Fakes\RecordingOtpChannel;

/**
 * T-251 part 1 (DOMAIN_LOGIC.md §3.4, Docs/TEST.md scenario 53) — one person, several IDs: the selected ID, login,
 * Switch ID and activation of a 2nd ID.
 */
function sipPerson(string $mobile, string $password = 'secret-pass'): User
{
    return User::factory()->create(['role' => 'member', 'mobile' => $mobile, 'password' => $password]);
}

function sipId(User $user, string $customerId, string $status = 'active'): Member
{
    return Member::create([
        'user_id' => $user->id,
        'customer_id' => $status === 'active' ? $customerId : null,
        'membership_plan_id' => MembershipPlan::where('code', 'E')->value('id'),
        'status' => $status,
        'activated_at' => $status === 'active' ? now() : null,
    ]);
}

beforeEach(function () {
    $this->seed(MembershipPlanSeeder::class);
    RecordingOtpChannel::$sent = [];
    $this->app->bind(EmailOtpChannel::class, RecordingOtpChannel::class);
    $this->app->bind(SmsOtpChannel::class, RecordingOtpChannel::class);
});

test('a person with one ID sees no Switch ID and keeps working exactly as before', function () {
    $user = sipPerson('9876511001');
    $only = sipId($user, 'SIP-ONE');

    expect($user->member->id)->toBe($only->id);

    $this->post('/member/login/password', ['customer_id' => 'SIP-ONE', 'password' => 'secret-pass'])->assertRedirect();

    $this->get('/dashboard')->assertInertia(fn ($page) => $page->where('memberIdSwitcher', null));
});

test('Customer ID login opens that ID; any of the person\'s Customer IDs works with the one password', function () {
    $user = sipPerson('9876511002');
    $first = sipId($user, 'SIP-A1');
    $second = sipId($user, 'SIP-A2');

    $this->post('/member/login/password', ['customer_id' => 'SIP-A2', 'password' => 'secret-pass'])->assertRedirect();

    $this->assertAuthenticatedAs($user);
    expect(session(MemberIds::SESSION_KEY))->toBe($second->id)
        ->and(auth()->user()->fresh()->member->id)->toBe($second->id);

    $this->get('/dashboard')->assertInertia(fn ($page) => $page
        ->has('memberIdSwitcher', 2)
        ->where('memberIdSwitcher.0.customer_id', 'SIP-A1')
        ->where('memberIdSwitcher.0.selected', false)
        ->where('memberIdSwitcher.1.customer_id', 'SIP-A2')
        ->where('memberIdSwitcher.1.selected', true));

    // The selection belongs to the signed-in person only: someone else's `member` is their first active ID.
    $other = sipPerson('9876511009');
    $otherFirst = sipId($other, 'SIP-O1');
    sipId($other, 'SIP-O2');
    expect($other->fresh()->member->id)->toBe($otherFirst->id)
        ->and($first->fresh()->user->member->id)->toBe($second->id);
});

test('OTP login opens the person\'s first active ID', function () {
    $user = sipPerson('9876511003');
    sipId($user, 'SIP-B0', 'payment_pending');
    $firstActive = sipId($user, 'SIP-B1');
    sipId($user, 'SIP-B2');

    $this->post('/member/login/otp/request', ['identifier' => '9876511003'])->assertSessionHasNoErrors();
    $this->post('/member/login/otp/verify', ['identifier' => '9876511003', 'otp' => RecordingOtpChannel::$sent['9876511003']])
        ->assertRedirect();

    expect(session(MemberIds::SESSION_KEY))->toBe($firstActive->id);
});

test('Switch ID moves every page to the chosen own active ID and refuses anything else', function () {
    $user = sipPerson('9876511004');
    $first = sipId($user, 'SIP-C1');
    $second = sipId($user, 'SIP-C2');
    $pending = sipId($user, 'SIP-C3', 'payment_pending');
    $stranger = sipId(sipPerson('9876511005'), 'SIP-X1');

    $this->actingAs($user)->withSession([MemberIds::SESSION_KEY => $first->id])
        ->post(route('member.switch-id'), ['member_id' => $second->id])
        ->assertRedirect(route('dashboard'));

    expect(session(MemberIds::SESSION_KEY))->toBe($second->id);

    $this->get(route('member.profile.show'))->assertOk();
    expect(auth()->user()->fresh()->member->id)->toBe($second->id);

    foreach ([$stranger->id, $pending->id, 999999] as $notAllowed) {
        $this->post(route('member.switch-id'), ['member_id' => $notAllowed])->assertSessionHasErrors('member_id');
        expect(session(MemberIds::SESSION_KEY))->toBe($second->id);
    }
});

test('a selected ID that no longer exists falls back to the first active ID', function () {
    $user = sipPerson('9876511006');
    $first = sipId($user, 'SIP-D1');

    $this->actingAs($user)->withSession([MemberIds::SESSION_KEY => 987654]);
    $this->get('/dashboard')->assertOk();

    expect(auth()->user()->fresh()->member->id)->toBe($first->id);
});

test('activating a 2nd ID never resets the password; the first ID\'s activation still sets it to the Customer ID', function () {
    $user = sipPerson('9876511007', 'my-own-password');
    $plan = MembershipPlan::where('code', 'E')->firstOrFail();
    $activate = app(ActivateMembershipOnPaymentConfirmed::class);

    $payFor = function (Member $member) use ($plan): Payment {
        return Payment::create([
            'member_id' => $member->id,
            'type' => 'registration',
            'amount' => $plan->amount,
            'mode' => 'cash',
            'status' => 'paid',
            'idempotency_key' => (string) Str::uuid(),
        ]);
    };

    $first = sipId($user, '', 'payment_pending');
    $second = sipId($user, '', 'payment_pending');

    $first = $activate($first, null, $payFor($first));
    expect(Hash::check($first->customer_id, $user->fresh()->password))->toBeTrue();

    $user->update(['password' => 'changed-later']);

    $second = $activate($second, null, $payFor($second));
    expect($second->customer_id)->not->toBeNull()
        ->and(Hash::check('changed-later', $user->fresh()->password))->toBeTrue();
});

test('the IDs-per-person limit is a rule value with a default of 11, editable on Rule Versions', function () {
    expect(MemberIds::limit())->toBe(11);

    $this->actingAs(User::factory()->create(['role' => 'super_admin']))
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('super-admin.rule-versions.store'), ['max_ids_per_person' => 7])
        ->assertSessionHasNoErrors();

    expect(MemberIds::limit())->toBe(7);
});

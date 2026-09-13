<?php

use App\Models\Member;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\Otp\EmailOtpChannel;
use App\Services\Otp\SmsOtpChannel;
use Illuminate\Support\Facades\Hash;
use Tests\Fakes\RecordingOtpChannel;

/**
 * Docs/TEST.md scenario 11 — Login & Authentication.
 */
function createActiveMemberWithLogin(string $customerId, string $email, string $mobile): Member
{
    $user = User::factory()->create([
        'role' => 'member',
        'email' => $email,
        'mobile' => $mobile,
        'password' => $customerId,
    ]);

    return Member::create([
        'user_id' => $user->id,
        'customer_id' => $customerId,
        'status' => 'active',
        'activated_at' => now(),
    ]);
}

beforeEach(function () {
    RecordingOtpChannel::$sent = [];
    $this->app->bind(EmailOtpChannel::class, RecordingOtpChannel::class);
    $this->app->bind(SmsOtpChannel::class, RecordingOtpChannel::class);
});

test('a pending (not yet activated) member cannot log in via any method', function () {
    $user = User::factory()->create(['role' => 'member', 'mobile' => '9876500010']);
    Member::create(['user_id' => $user->id, 'customer_id' => null, 'status' => 'payment_pending']);

    $this->post('/member/login/otp/request', ['identifier' => $user->mobile])
        ->assertSessionHasErrors('identifier');

    $this->post('/member/login/password', ['customer_id' => 'GWL999', 'password' => 'GWL999'])
        ->assertSessionHasErrors('customer_id');

    $this->assertGuest();
});

test('OTP login works via either the registered mobile or email, and Customer ID + default password logs in', function () {
    $member = createActiveMemberWithLogin('GWL045', 'member045@example.test', '9876500011');

    // Customer ID + default password (= Customer ID itself).
    $this->post('/member/login/password', ['customer_id' => 'GWL045', 'password' => 'GWL045'])
        ->assertRedirect();
    $this->assertAuthenticatedAs($member->user);
    auth()->logout();

    // OTP via mobile.
    $this->post('/member/login/otp/request', ['identifier' => '9876500011'])->assertSessionHasNoErrors();
    $code = RecordingOtpChannel::$sent['9876500011'];
    $this->post('/member/login/otp/verify', ['identifier' => '9876500011', 'otp' => $code])->assertRedirect();
    $this->assertAuthenticatedAs($member->user);
    auth()->logout();

    // OTP via email — regression: both identifiers must work, not just mobile.
    $this->post('/member/login/otp/request', ['identifier' => 'member045@example.test'])->assertSessionHasNoErrors();
    $code = RecordingOtpChannel::$sent['member045@example.test'];
    $this->post('/member/login/otp/verify', ['identifier' => 'member045@example.test', 'otp' => $code])->assertRedirect();
    $this->assertAuthenticatedAs($member->user);
});

test('password reset requires OTP verification first — no bypass, even with the correct current password', function () {
    $member = createActiveMemberWithLogin('GWL050', 'member050@example.test', '9876500012');

    // Attempting to set a new password without ever verifying an OTP is rejected.
    $this->post('/member/password/set', [
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertSessionHasErrors('password');

    $member->user->refresh();
    expect(Hash::check('GWL050', $member->user->password))->toBeTrue();

    // Correct flow: request OTP, verify it, then set password.
    $this->post('/member/password/otp/request', ['identifier' => 'member050@example.test'])->assertSessionHasNoErrors();
    $code = RecordingOtpChannel::$sent['member050@example.test'];

    $this->post('/member/password/otp/verify', ['identifier' => 'member050@example.test', 'otp' => $code])
        ->assertSessionHasNoErrors();

    $this->post('/member/password/set', [
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertRedirect();

    $member->user->refresh();
    expect(Hash::check('NewPassword123!', $member->user->password))->toBeTrue();
    $this->assertAuthenticatedAs($member->user);
});

test('OTP attempts are capped and an expired code is rejected', function () {
    createActiveMemberWithLogin('GWL060', 'member060@example.test', '9876500013');

    $this->post('/member/login/otp/request', ['identifier' => 'member060@example.test']);

    for ($i = 0; $i < 5; $i++) {
        $this->post('/member/login/otp/verify', ['identifier' => 'member060@example.test', 'otp' => '000000'])
            ->assertSessionHasErrors('otp');
    }

    $correctCode = RecordingOtpChannel::$sent['member060@example.test'];

    // Attempts exhausted — even the correct code is now rejected.
    $this->post('/member/login/otp/verify', ['identifier' => 'member060@example.test', 'otp' => $correctCode])
        ->assertSessionHasErrors('otp');

    $otp = OtpCode::where('identifier', 'member060@example.test')->latest('id')->first();
    expect($otp->consumed_at)->toBeNull();
});

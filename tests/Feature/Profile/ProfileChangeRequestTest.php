<?php

use App\Actions\Payout\SubmitPayoutRequest;
use App\Actions\Profile\ApproveProfileChangeRequest;
use App\Actions\Profile\RejectProfileChangeRequest;
use App\Actions\Profile\SubmitPendingProfileFields;
use App\Actions\Profile\SubmitProfileChangeRequest;
use App\Actions\Profile\VerifyMemberBankDetail;
use App\Models\Member;
use App\Models\MemberBankDetail;
use App\Models\User;
use App\Notifications\ProfileChangeRequestReviewed;
use App\Services\WalletLedgerService;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §13 (Profile & Post-Registration Pending Fields),
 * Docs/TEST.md scenario 13 — one-time Pending Fields submission, its lock,
 * and the Change Request submit/review workflow with its notification.
 */
function profileMember(string $customerId): Member
{
    $user = User::factory()->create(['role' => 'member']);

    return Member::create([
        'user_id' => $user->id,
        'customer_id' => $customerId,
        'status' => 'active',
        'activated_at' => now(),
    ]);
}

function superAdminOperator(): User
{
    return User::factory()->create(['role' => 'super_admin']);
}

function validBankDetails(): array
{
    return [
        'account_holder_name' => 'Test Holder',
        'account_number' => '1234567890',
        'ifsc_code' => 'TEST0001234',
        'bank_name' => 'Test Bank',
        'proof_document_path' => 'proofs/cheque-1.jpg',
    ];
}

beforeEach(function () {
    $this->seed();
});

function submitPendingFields(Member $member): Member
{
    return app(SubmitPendingProfileFields::class)(
        $member,
        'ABCDE1234F',
        '123456789012',
        'photos/profile-1.jpg',
        '123 Test Street',
        validBankDetails(),
    );
}

test('submitting pending fields sets all fields and creates one unverified bank detail row', function () {
    $member = profileMember('PCR-SUBMIT-1');

    $updated = submitPendingFields($member);

    expect($updated->pan_card)->toBe('ABCDE1234F');
    expect($updated->aadhaar_card)->toBe('123456789012');
    expect($updated->profile_photo_path)->toBe('photos/profile-1.jpg');
    expect($updated->address)->toBe('123 Test Street');
    expect($updated->pending_fields_submitted_at)->not->toBeNull();

    $bankDetail = MemberBankDetail::where('member_id', $member->id)->first();
    expect($bankDetail)->not->toBeNull();
    expect($bankDetail->account_number)->toBe('1234567890');
    expect($bankDetail->verified_at)->toBeNull();
});

test('a second pending-fields submission is rejected — fields lock after the first', function () {
    $member = profileMember('PCR-LOCK-1');
    submitPendingFields($member);

    expect(fn () => app(SubmitPendingProfileFields::class)(
        $member->fresh(),
        'ZYXWV9876G',
        '999999999999',
        'photos/other.jpg',
        'Different Address',
        validBankDetails(),
    ))->toThrow(ValidationException::class);

    expect($member->fresh()->pan_card)->toBe('ABCDE1234F');
});

test('invalid PAN or Aadhaar format is rejected before anything is saved', function () {
    $member = profileMember('PCR-INVALID-1');

    expect(fn () => app(SubmitPendingProfileFields::class)(
        $member,
        'INVALID123',
        '123456789012',
        'photos/profile-1.jpg',
        '123 Test Street',
        validBankDetails(),
    ))->toThrow(ValidationException::class);
    expect($member->fresh()->pending_fields_submitted_at)->toBeNull();

    expect(fn () => app(SubmitPendingProfileFields::class)(
        $member,
        'ABCDE1234F',
        '12345',
        'photos/profile-1.jpg',
        '123 Test Street',
        validBankDetails(),
    ))->toThrow(ValidationException::class);
    expect($member->fresh()->pending_fields_submitted_at)->toBeNull();
    expect(MemberBankDetail::where('member_id', $member->id)->count())->toBe(0);
});

test('a verified bank detail can be used for a payout request', function () {
    $member = profileMember('PCR-VERIFY-1');
    submitPendingFields($member);
    $bankDetail = MemberBankDetail::where('member_id', $member->id)->firstOrFail();

    app(WalletLedgerService::class)->credit($member->fresh(), 'level_income', 10000, null, 'seed');

    expect(fn () => app(SubmitPayoutRequest::class)($member->fresh(), 5000, $bankDetail))
        ->toThrow(ValidationException::class);

    app(VerifyMemberBankDetail::class)($bankDetail, superAdminOperator());

    $request = app(SubmitPayoutRequest::class)($member->fresh(), 5000, $bankDetail->fresh());
    expect($request->status)->toBe('pending');
});

test('a change request snapshots the old value and does not touch the field until reviewed', function () {
    $member = profileMember('PCR-REQUEST-1');
    submitPendingFields($member);

    $request = app(SubmitProfileChangeRequest::class)($member->fresh(), 'pan_card', 'ZYXWV9876G', 'typo in original submission');

    expect($request->field_name)->toBe('pan_card');
    expect($request->old_value)->toBe('ABCDE1234F');
    expect($request->new_value)->toBe('ZYXWV9876G');
    expect($request->status)->toBe('pending');
    expect($member->fresh()->pan_card)->toBe('ABCDE1234F');
});

test('a second pending request for the same field is rejected', function () {
    $member = profileMember('PCR-DUP-1');
    submitPendingFields($member);
    app(SubmitProfileChangeRequest::class)($member->fresh(), 'pan_card', 'ZYXWV9876G');

    expect(fn () => app(SubmitProfileChangeRequest::class)($member->fresh(), 'pan_card', 'DIFFERENT1A'))
        ->toThrow(ValidationException::class);
});

test('approving a change request updates the field, marks the request approved, and notifies the member', function () {
    $member = profileMember('PCR-APPROVE-1');
    submitPendingFields($member);
    $request = app(SubmitProfileChangeRequest::class)($member->fresh(), 'pan_card', 'ZYXWV9876G');
    $operator = superAdminOperator();

    $approved = app(ApproveProfileChangeRequest::class)($request, $operator);

    expect($approved->status)->toBe('approved');
    expect($approved->reviewed_by)->toBe($operator->id);
    expect($member->fresh()->pan_card)->toBe('ZYXWV9876G');

    $notification = DatabaseNotification::where('notifiable_id', $member->id)
        ->where('type', ProfileChangeRequestReviewed::class)
        ->first();
    expect($notification)->not->toBeNull();
});

test('rejecting a change request leaves the field unchanged, records a reason, and still notifies the member', function () {
    $member = profileMember('PCR-REJECT-1');
    submitPendingFields($member);
    $request = app(SubmitProfileChangeRequest::class)($member->fresh(), 'address', 'New Address');
    $operator = superAdminOperator();

    $rejected = app(RejectProfileChangeRequest::class)($request, $operator, 'address proof mismatch');

    expect($rejected->status)->toBe('rejected');
    expect($rejected->rejection_reason)->toBe('address proof mismatch');
    expect($member->fresh()->address)->toBe('123 Test Street');

    $notification = DatabaseNotification::where('notifiable_id', $member->id)
        ->where('type', ProfileChangeRequestReviewed::class)
        ->first();
    expect($notification)->not->toBeNull();
});

test('an approved bank_details change updates the same row in place and resets verified_at', function () {
    $member = profileMember('PCR-BANK-1');
    submitPendingFields($member);
    $bankDetail = MemberBankDetail::where('member_id', $member->id)->firstOrFail();
    app(VerifyMemberBankDetail::class)($bankDetail, superAdminOperator());
    expect($bankDetail->fresh()->verified_at)->not->toBeNull();

    $newDetails = [
        'account_holder_name' => 'Test Holder',
        'account_number' => '9999999999',
        'ifsc_code' => 'TEST0009999',
        'bank_name' => 'New Bank',
    ];
    $request = app(SubmitProfileChangeRequest::class)($member->fresh(), 'bank_details', $newDetails);
    app(ApproveProfileChangeRequest::class)($request, superAdminOperator());

    expect(MemberBankDetail::where('member_id', $member->id)->count())->toBe(1); // Updated in place, not a second row.
    $fresh = $bankDetail->fresh();
    expect($fresh->account_number)->toBe('9999999999');
    expect($fresh->bank_name)->toBe('New Bank');
    expect($fresh->verified_at)->toBeNull(); // Must be re-verified after the change.
});

<?php

namespace App\Actions\Profile;

use App\Models\Member;
use App\Models\MemberBankDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §13 — the one-time Pending Fields submission (PAN, Aadhaar,
 * Profile Photo, Bank Account Details, Passbook/Cancelled Cheque, Address),
 * locked after the first successful submission. "Bank Account Details" and
 * "Passbook/Cancelled Cheque" together create the member's one
 * `member_bank_details` row rather than separate `members` columns
 * (DOMAIN_LOGIC.md §21 T-012 pre-coding pass) — created unverified;
 * `VerifyMemberBankDetail` is a separate Super Admin step before it can be
 * used for a payout request (§11.2 point 8).
 */
class SubmitPendingProfileFields
{
    private const PAN_PATTERN = '/^[A-Z]{5}[0-9]{4}[A-Z]$/';

    private const AADHAAR_PATTERN = '/^[0-9]{12}$/';

    /**
     * @param  array{account_holder_name: string, account_number: string, ifsc_code: string, bank_name: string, proof_document_path: string}  $bankDetails
     */
    public function __invoke(
        Member $member,
        string $panCard,
        string $aadhaarCard,
        string $profilePhotoPath,
        string $address,
        array $bankDetails,
    ): Member {
        if ($member->pending_fields_submitted_at !== null) {
            throw ValidationException::withMessages([
                'pending_fields' => 'Pending fields have already been submitted and are locked.',
            ]);
        }

        if (! preg_match(self::PAN_PATTERN, $panCard)) {
            throw ValidationException::withMessages([
                'pan_card' => 'Invalid PAN format.',
            ]);
        }

        if (! preg_match(self::AADHAAR_PATTERN, $aadhaarCard)) {
            throw ValidationException::withMessages([
                'aadhaar_card' => 'Invalid Aadhaar format.',
            ]);
        }

        return DB::transaction(function () use ($member, $panCard, $aadhaarCard, $profilePhotoPath, $address, $bankDetails) {
            $locked = Member::whereKey($member->id)->lockForUpdate()->firstOrFail();

            if ($locked->pending_fields_submitted_at !== null) {
                throw ValidationException::withMessages([
                    'pending_fields' => 'Pending fields have already been submitted and are locked.',
                ]);
            }

            $locked->update([
                'pan_card' => $panCard,
                'aadhaar_card' => $aadhaarCard,
                'profile_photo_path' => $profilePhotoPath,
                'address' => $address,
                'pending_fields_submitted_at' => now(),
            ]);

            MemberBankDetail::create([
                'member_id' => $locked->id,
                'account_holder_name' => $bankDetails['account_holder_name'],
                'account_number' => $bankDetails['account_number'],
                'ifsc_code' => $bankDetails['ifsc_code'],
                'bank_name' => $bankDetails['bank_name'],
                'proof_document_path' => $bankDetails['proof_document_path'],
            ]);

            return $locked->fresh();
        });
    }
}

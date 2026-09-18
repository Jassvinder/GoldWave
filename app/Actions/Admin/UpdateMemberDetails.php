<?php

namespace App\Actions\Admin;

use App\Models\Member;
use App\Models\MemberBankDetail;
use Illuminate\Support\Facades\DB;

/**
 * T-106 (17-09-2026) — Super Admin's direct Member edit, per the user's
 * explicit instruction: every field except `customer_id` is editable here,
 * bypassing `Profile\SubmitProfileChangeRequest`'s approval workflow (that
 * stays the channel for member-initiated edits). Every field is optional —
 * only what's actually passed is updated, so a Super Admin can fix just one
 * field without needing every other one filled in first. If the bank
 * details actually change (not just resubmitted unchanged), `verified_at`/
 * `verified_by` reset to null, mirroring `ApproveProfileChangeRequest`'s
 * same rule — a changed beneficiary account must be re-verified. Filling in
 * a previously-empty pending field (PAN/Aadhaar/photo/address/bank) also
 * stamps `pending_fields_submitted_at` if it was still null, so the
 * member's own one-time Pending Fields form correctly shows as already
 * complete rather than prompting them to fill in data the Super Admin just
 * entered on their behalf.
 */
class UpdateMemberDetails
{
    /** @param  array{account_holder_name?: string, account_number?: string, ifsc_code?: string, bank_name?: string, proof_document_path?: string}|null  $bankDetails */
    public function __invoke(
        Member $member,
        string $name,
        string $email,
        ?string $mobile,
        ?string $panCard,
        ?string $aadhaarCard,
        ?string $address,
        ?string $profilePhotoPath,
        ?array $bankDetails,
    ): Member {
        return DB::transaction(function () use ($member, $name, $email, $mobile, $panCard, $aadhaarCard, $address, $profilePhotoPath, $bankDetails) {
            $locked = Member::whereKey($member->id)->lockForUpdate()->firstOrFail();

            $locked->user?->update([
                'name' => $name,
                'email' => $email,
                'mobile' => $mobile,
            ]);

            $memberUpdates = array_filter([
                'pan_card' => $panCard,
                'aadhaar_card' => $aadhaarCard,
                'address' => $address,
                'profile_photo_path' => $profilePhotoPath,
            ], fn ($value) => $value !== null);

            if ($memberUpdates !== [] && $locked->pending_fields_submitted_at === null) {
                $memberUpdates['pending_fields_submitted_at'] = now();
            }

            if ($memberUpdates !== []) {
                $locked->update($memberUpdates);
            }

            if ($bankDetails !== null) {
                $this->applyBankDetails($locked, $bankDetails);
            }

            return $locked->fresh();
        });
    }

    /** @param  array{account_holder_name?: string, account_number?: string, ifsc_code?: string, bank_name?: string, proof_document_path?: string}  $bankDetails */
    private function applyBankDetails(Member $member, array $bankDetails): void
    {
        $existing = MemberBankDetail::where('member_id', $member->id)->lockForUpdate()->first();

        $coreFields = array_filter([
            'account_holder_name' => $bankDetails['account_holder_name'] ?? null,
            'account_number' => $bankDetails['account_number'] ?? null,
            'ifsc_code' => $bankDetails['ifsc_code'] ?? null,
            'bank_name' => $bankDetails['bank_name'] ?? null,
        ], fn ($value) => $value !== null);

        $changed = $existing === null;

        if ($existing !== null) {
            foreach ($coreFields as $key => $value) {
                if ($existing->{$key} !== $value) {
                    $changed = true;
                }
            }
        }

        $updates = $coreFields;

        if (isset($bankDetails['proof_document_path'])) {
            $updates['proof_document_path'] = $bankDetails['proof_document_path'];
        }

        if ($changed) {
            $updates['verified_by'] = null;
            $updates['verified_at'] = null;
        }

        if ($existing) {
            $existing->update($updates);

            return;
        }

        MemberBankDetail::create([...$updates, 'member_id' => $member->id]);
    }
}

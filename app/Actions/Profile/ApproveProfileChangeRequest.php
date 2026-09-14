<?php

namespace App\Actions\Profile;

use App\Models\MemberBankDetail;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use App\Notifications\ProfileChangeRequestReviewed;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §13 point 3 — Super Admin approves a pending Change
 * Request: the underlying field is updated to the requested new value, and
 * the member is notified. A `bank_details` approval updates the member's
 * existing `member_bank_details` row in place and resets `verified_at` to
 * null — a changed beneficiary account must be re-verified before its next
 * payout use (DOMAIN_LOGIC.md §21 T-012 pre-coding pass).
 */
class ApproveProfileChangeRequest
{
    private const SIMPLE_FIELDS = ['pan_card', 'aadhaar_card', 'profile_photo_path', 'address'];

    public function __invoke(ProfileChangeRequest $changeRequest, User $operator): ProfileChangeRequest
    {
        $reviewed = DB::transaction(function () use ($changeRequest, $operator) {
            $locked = ProfileChangeRequest::whereKey($changeRequest->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'change_request' => 'Only a pending change request can be approved.',
                ]);
            }

            $member = $locked->member()->lockForUpdate()->firstOrFail();

            if (in_array($locked->field_name, self::SIMPLE_FIELDS, true)) {
                $member->update([$locked->field_name => $locked->new_value]);
            } else {
                $this->applyBankDetailsChange($member->id, $locked->new_value);
            }

            $locked->update([
                'status' => 'approved',
                'reviewed_by' => $operator->id,
                'reviewed_at' => now(),
            ]);

            return $locked->fresh();
        });

        $reviewed->member()->firstOrFail()->notify(new ProfileChangeRequestReviewed($reviewed));

        return $reviewed;
    }

    private function applyBankDetailsChange(int $memberId, string $newValueJson): void
    {
        $newValue = (array) json_decode($newValueJson, true);

        $bankDetail = MemberBankDetail::where('member_id', $memberId)->lockForUpdate()->firstOrFail();

        $bankDetail->update([
            'account_holder_name' => (string) ($newValue['account_holder_name'] ?? ''),
            'account_number' => (string) ($newValue['account_number'] ?? ''),
            'ifsc_code' => (string) ($newValue['ifsc_code'] ?? ''),
            'bank_name' => (string) ($newValue['bank_name'] ?? ''),
            'verified_by' => null,
            'verified_at' => null,
        ]);
    }
}

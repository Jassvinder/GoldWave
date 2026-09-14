<?php

namespace App\Actions\Profile;

use App\Models\Member;
use App\Models\ProfileChangeRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §13 point 3 — once a pending field is locked, any change
 * goes through this request/approval workflow: member submits reason + new
 * value, the system snapshots the old value, Super Admin reviews later
 * (Approve/RejectProfileChangeRequest). Guarded against a second in-flight
 * request for the same field (DOMAIN_LOGIC.md §21 T-012 pre-coding pass).
 */
class SubmitProfileChangeRequest
{
    private const SIMPLE_FIELDS = ['pan_card', 'aadhaar_card', 'profile_photo_path', 'address'];

    private const ALLOWED_FIELDS = ['pan_card', 'aadhaar_card', 'profile_photo_path', 'address', 'bank_details'];

    /** @param  string|array<string, string>  $newValue */
    public function __invoke(Member $member, string $fieldName, string|array $newValue, ?string $reason = null): ProfileChangeRequest
    {
        if (! in_array($fieldName, self::ALLOWED_FIELDS, true)) {
            throw ValidationException::withMessages([
                'field_name' => 'Not a recognized profile field.',
            ]);
        }

        if ($member->pending_fields_submitted_at === null) {
            throw ValidationException::withMessages([
                'pending_fields' => 'Pending fields must be submitted before a change can be requested.',
            ]);
        }

        return DB::transaction(function () use ($member, $fieldName, $newValue, $reason) {
            $exists = ProfileChangeRequest::where('member_id', $member->id)
                ->where('field_name', $fieldName)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'field_name' => 'A pending change request already exists for this field.',
                ]);
            }

            return ProfileChangeRequest::create([
                'member_id' => $member->id,
                'field_name' => $fieldName,
                'old_value' => $this->currentValue($member, $fieldName),
                'new_value' => $this->encodeNewValue($newValue),
                'reason' => $reason,
                'status' => 'pending',
            ]);
        });
    }

    /** @param  string|array<string, string>  $newValue */
    private function encodeNewValue(string|array $newValue): string
    {
        if (! is_array($newValue)) {
            return $newValue;
        }

        $encoded = json_encode($newValue);

        if ($encoded === false) {
            throw ValidationException::withMessages([
                'new_value' => 'The new value could not be encoded.',
            ]);
        }

        return $encoded;
    }

    private function currentValue(Member $member, string $fieldName): ?string
    {
        if (in_array($fieldName, self::SIMPLE_FIELDS, true)) {
            return $member->{$fieldName};
        }

        $bankDetail = $member->bankDetails()->latest('id')->first();

        if (! $bankDetail) {
            return null;
        }

        $encoded = json_encode([
            'account_holder_name' => $bankDetail->account_holder_name,
            'account_number' => $bankDetail->account_number,
            'ifsc_code' => $bankDetail->ifsc_code,
            'bank_name' => $bankDetail->bank_name,
        ]);

        return $encoded === false ? null : $encoded;
    }
}

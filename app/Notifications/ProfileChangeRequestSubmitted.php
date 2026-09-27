<?php

namespace App\Notifications;

use App\Models\ProfileChangeRequest;

/** Super Admin: a member asked to change a locked profile field. */
class ProfileChangeRequestSubmitted extends AppNotification
{
    public function __construct(public readonly ProfileChangeRequest $changeRequest) {}

    public function key(): string
    {
        return 'profile_change_request_submitted';
    }

    public function category(): string
    {
        return 'request';
    }

    public function title(): string
    {
        return 'Profile change request';
    }

    public function body(): string
    {
        $member = $this->changeRequest->member;
        $who = trim(($member->user->name ?? 'A member').($member->customer_id ? " ({$member->customer_id})" : ''));

        return "{$who} asked to change their ".self::fieldLabel($this->changeRequest->field_name).'.';
    }

    public function url(): string
    {
        return '/super-admin/profile-change-requests';
    }

    public static function fieldLabel(string $fieldName): string
    {
        return match ($fieldName) {
            'pan_card' => 'PAN card',
            'aadhaar_card' => 'Aadhaar card',
            'address' => 'address',
            'profile_photo_path' => 'profile photo',
            'bank_details' => 'bank details',
            default => str_replace('_', ' ', $fieldName),
        };
    }
}

<?php

namespace App\Notifications;

use App\Models\ProfileChangeRequest;

/**
 * DOMAIN_LOGIC.md §13 point 3 — the member is told once the Super Admin approves or rejects their Change Request
 * (bell + email + SMS since T-141; database-only before that, attached to the Member — those old rows were moved to the
 * member's user by `move_member_notifications_to_users`).
 */
class ProfileChangeRequestReviewed extends AppNotification
{
    public function __construct(public readonly ProfileChangeRequest $changeRequest) {}

    public function key(): string
    {
        return 'profile_change_request_reviewed';
    }

    public function category(): string
    {
        return 'request';
    }

    public function title(): string
    {
        return $this->changeRequest->status === 'approved' ? 'Change request approved' : 'Change request rejected';
    }

    public function body(): string
    {
        $field = ProfileChangeRequestSubmitted::fieldLabel($this->changeRequest->field_name);

        if ($this->changeRequest->status === 'approved') {
            return "Your request to change your {$field} was approved.";
        }

        $reason = $this->changeRequest->rejection_reason;

        return "Your request to change your {$field} was rejected".($reason ? ": {$reason}" : '.');
    }

    public function url(): string
    {
        return '/member/change-requests';
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return array_merge(parent::toArray($notifiable), [
            'profile_change_request_id' => $this->changeRequest->id,
            'field_name' => $this->changeRequest->field_name,
            'status' => $this->changeRequest->status,
            'rejection_reason' => $this->changeRequest->rejection_reason,
        ]);
    }
}

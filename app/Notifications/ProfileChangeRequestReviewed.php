<?php

namespace App\Notifications;

use App\Models\ProfileChangeRequest;
use Illuminate\Notifications\Notification;

/**
 * DOMAIN_LOGIC.md §13 point 3 — the member is notified once Super Admin
 * approves or rejects their Change Request. Database channel only (the
 * first notification this project sends) — a dedicated Notifications page
 * is Member Portal scope, T-015's job (DOMAIN_LOGIC.md §21 T-012 pre-coding
 * pass).
 */
class ProfileChangeRequestReviewed extends Notification
{
    public function __construct(public readonly ProfileChangeRequest $changeRequest) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'profile_change_request_id' => $this->changeRequest->id,
            'field_name' => $this->changeRequest->field_name,
            'status' => $this->changeRequest->status,
            'rejection_reason' => $this->changeRequest->rejection_reason,
        ];
    }
}

<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * T-140 — the one way business code sends a notification. A failing channel (mail server down, SMS provider error) is
 * logged and swallowed: a notification problem must never fail, or roll back, the payment/request/approval that
 * triggered it. Notifications are per User (the bell and the Notifications page read `users`' notifications).
 */
class Notifier
{
    public static function toUser(?User $user, AppNotification $notification): void
    {
        if ($user === null) {
            return;
        }

        try {
            $user->notify($notification);
        } catch (Throwable $e) {
            Log::error('Notification could not be sent', [
                'notification' => $notification->key(),
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Every company-side user — Super Admins and (since 29-09-2026) Admins — gets the company alerts. */
    public static function toSuperAdmins(AppNotification $notification): void
    {
        User::whereIn('role', ['super_admin', 'admin'])->get()->each(fn (User $admin) => self::toUser($admin, $notification));
    }
}

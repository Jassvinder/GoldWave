<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * INSTRUCTIONS.md M18 — system notifications plus request/status tracking.
 * The "request/status tracking" half (profile change requests, cash payment
 * status) is already its own page (M04) / visible on Payment History (M07) —
 * this page is the actual Laravel database-notification inbox
 * (`ProfileChangeRequestReviewed`, T-012, is the only notification type any
 * task has sent so far).
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        $notifications = $member->notifications()
            ->latest()
            ->get()
            ->map(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'type' => class_basename($notification->type),
                'data' => $notification->data,
                'read_at' => $notification->read_at?->toDateString(),
                'created_at' => $notification->created_at?->toDateString(),
            ]);

        return Inertia::render('member/notifications', [
            'notifications' => $notifications,
        ]);
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        $member->notifications()->where('id', $notification)->firstOrFail()->markAsRead();

        return redirect()->route('member.notifications.index');
    }
}

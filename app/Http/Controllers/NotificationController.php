<?php

namespace App\Http\Controllers;

use App\Services\NotificationFeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-140 — the Notifications pages of all three portals (Member, Super Admin, Admin/Store Owner; the route's `portal`
 * default picks the Inertia page) and the read-state endpoints the bell and the pages use. Every query is scoped to the
 * logged-in user's own notifications, so another user's notification id is simply a 404.
 */
class NotificationController extends Controller
{
    public function index(Request $request, NotificationFeed $feed): Response
    {
        $portal = (string) $request->route('portal');

        return Inertia::render("{$portal}/notifications", $feed->page(
            $request->user(),
            $request->string('category')->toString() ?: null,
            $request->boolean('unread'),
        ));
    }

    /** Marks the notification read, then goes to its target (or back to the portal's Notifications page). */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $found = $request->user()->notifications()->where('id', $notification)->firstOrFail();
        $found->markAsRead();

        $target = $found->data['url'] ?? null;

        // Only ever follow an internal path — never an absolute or protocol-relative URL stored in the data.
        $internal = is_string($target) && str_starts_with($target, '/') && ! str_starts_with($target, '//');

        return redirect($internal ? $target : NotificationFeed::indexUrl($request->user(), $request));
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->where('id', $notification)->firstOrFail()->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}

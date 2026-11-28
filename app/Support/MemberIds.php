<?php

namespace App\Support;

use App\Models\Member;
use App\Models\User;
use App\Services\RuleVersionService;

/**
 * DOMAIN_LOGIC.md §3.4 (T-251) — one person (user) may hold several member IDs. The signed-in person works on one of
 * them at a time: the "selected ID", kept in the session. `User::member()` reads it, so every page that already uses
 * `$user->member` shows the selected ID without change (ARCHITECTURE.md "Several IDs for one person").
 */
final class MemberIds
{
    public const SESSION_KEY = 'current_member_id';

    public const DEFAULT_LIMIT = 11;

    /** The most IDs one person may hold, pending ones included (rule value `max_ids_per_person`). */
    public static function limit(): int
    {
        return max(1, (int) app(RuleVersionService::class)->value('max_ids_per_person', self::DEFAULT_LIMIT));
    }

    /**
     * The ID the signed-in person chose, or null. Only the signed-in user has a selection: for anyone else
     * (notifications, eager loads, reports) `User::member()` falls back to their first active ID.
     */
    public static function selectedIdFor(User $user): ?int
    {
        // A blank model (eager loading builds the relation on one) is nobody's selection.
        if (! $user->exists || ! app()->bound('request')) {
            return null;
        }

        $request = request();

        if (! $request->hasSession() || $request->user()?->id !== $user->id) {
            return null;
        }

        $id = $request->session()->get(self::SESSION_KEY);

        return is_numeric($id) ? (int) $id : null;
    }

    /** Makes $member the signed-in person's selected ID. The caller checks that it belongs to them. */
    public static function select(Member $member): void
    {
        session([self::SESSION_KEY => $member->id]);
    }
}

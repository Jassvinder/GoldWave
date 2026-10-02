import type { Paginated } from './pagination';

/** One notification as shown in the bell and on the Notifications pages (built by `App\Services\NotificationFeed`). */
export type NotificationItem = {
    id: string;
    category: string;
    title: string;
    body: string;
    /** Goes through `notifications/{id}/open`: marks the notification read, then redirects to its target. */
    open_url: string;
    has_target: boolean;
    read: boolean;
    created_at: string;
};

/** The shared Inertia `notifications` prop behind the header bell. */
export type NotificationBellData = {
    unread_count: number;
    latest: NotificationItem[];
    index_url: string;
};

export type NotificationCounts = {
    total: number;
    unread: number;
    categories: Record<string, { total: number; unread: number }>;
};

export type NotificationsPageProps = {
    // Named "list", not "notifications" — the latter is the shared header-bell prop injected on every page
    // (HandleInertiaRequests), and a same-named page prop overrides it wherever the two get merged, crashing the bell.
    list: Paginated<NotificationItem>;
    counts: NotificationCounts;
    filters: { category: string | null; unread: boolean };
};

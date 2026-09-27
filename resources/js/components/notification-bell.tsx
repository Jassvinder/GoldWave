import { Link, router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useState } from 'react';
import { NotificationIcon } from '@/components/notification-meta';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { timeAgo } from '@/lib/utils';
import { readAll } from '@/routes/notifications';
import type { NotificationBellData } from '@/types/notifications';

/**
 * The header bell (T-140), shown in every portal. A red badge carries the unread count (9+ when large, hidden at 0); the
 * panel lists the latest notifications — clicking one marks it read and opens its target — with "Mark all as read" and a
 * link to the full Notifications page. Data comes from the shared Inertia `notifications` prop, so it is fresh on every
 * page visit.
 */
export function NotificationBell() {
    const bell = usePage().props.notifications as
        | NotificationBellData
        | null
        | undefined;
    const [open, setOpen] = useState(false);

    if (!bell) {
        return null;
    }

    const unread = bell.unread_count;
    const badge = unread > 9 ? '9+' : String(unread);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label={
                        unread > 0
                            ? `Notifications, ${unread} unread`
                            : 'Notifications'
                    }
                >
                    <Bell className="size-5" />
                    {unread > 0 && (
                        <span className="bg-destructive absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] leading-none font-semibold text-white">
                            {badge}
                        </span>
                    )}
                </Button>
            </PopoverTrigger>

            <PopoverContent
                align="end"
                className="w-[22rem] max-w-[calc(100vw-1.5rem)] p-0"
            >
                <div className="flex items-center justify-between gap-2 border-b px-4 py-3">
                    <h2 className="text-sm font-semibold">Notifications</h2>
                    {unread > 0 && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="text-muted-foreground h-7 px-2 text-xs"
                            onClick={() =>
                                router.post(
                                    readAll.url(),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Mark all as read
                        </Button>
                    )}
                </div>

                {bell.latest.length === 0 ? (
                    <div className="text-muted-foreground flex flex-col items-center gap-2 px-4 py-10 text-center text-sm">
                        <Bell className="size-6 opacity-40" />
                        <p>You&apos;re all caught up.</p>
                        <p className="text-xs">
                            New payments, requests and reminders will show up
                            here.
                        </p>
                    </div>
                ) : (
                    <ul className="max-h-96 divide-y overflow-y-auto">
                        {bell.latest.map((item) => (
                            <li key={item.id}>
                                <Link
                                    href={item.open_url}
                                    onClick={() => setOpen(false)}
                                    className="hover:bg-muted/60 flex items-start gap-3 px-4 py-3 transition-colors"
                                >
                                    <NotificationIcon
                                        category={item.category}
                                    />
                                    <span className="min-w-0 flex-1">
                                        <span
                                            className={`block text-sm leading-snug ${item.read ? 'text-muted-foreground font-medium' : 'font-semibold'}`}
                                        >
                                            {item.title}
                                        </span>
                                        <span className="text-muted-foreground mt-0.5 line-clamp-2 block text-xs">
                                            {item.body}
                                        </span>
                                        <span className="text-muted-foreground/80 mt-1 block text-[11px]">
                                            {timeAgo(item.created_at)}
                                        </span>
                                    </span>
                                    {!item.read && (
                                        <span
                                            className="bg-primary mt-1.5 size-2 shrink-0 rounded-full"
                                            aria-label="Unread"
                                        />
                                    )}
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}

                <div className="border-t p-1.5">
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="w-full justify-center"
                    >
                        <Link
                            href={bell.index_url}
                            onClick={() => setOpen(false)}
                        >
                            View all notifications
                        </Link>
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
    );
}

import { Head, Link, router, usePage } from '@inertiajs/react';
import { BellOff, CheckCheck } from 'lucide-react';
import { DataPagination } from '@/components/data-pagination';
import {
    CATEGORY_ORDER,
    NotificationIcon,
    categoryMeta,
} from '@/components/notification-meta';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { formatDateTime, timeAgo } from '@/lib/utils';
import { read, readAll } from '@/routes/notifications';
import type { NotificationsPageProps } from '@/types/notifications';

/**
 * The Notifications page shared by the Member, Super Admin and Admin/Store Owner portals (T-140). Category tabs show only
 * the categories that have items, each with its unread count; "Unread only" narrows the list; every row has explicit
 * "View" (marks read and opens the target) and "Mark as read" buttons — no whole-row click. The filters live in the URL,
 * so the browser's Back button and a page refresh keep them.
 */
export function NotificationsPage({
    list,
    counts,
    filters,
}: NotificationsPageProps) {
    const path = usePage().url.split('?')[0];
    const { category, unread } = filters;

    const visit = (params: Record<string, string | number | undefined>) =>
        router.get(path, params, { preserveScroll: true });

    const tabs = [
        { key: null, label: 'All', unread: counts.unread, total: counts.total },
        ...CATEGORY_ORDER.filter((key) => counts.categories[key]).map(
            (key) => ({
                key,
                label: categoryMeta(key).label,
                unread: counts.categories[key].unread,
                total: counts.categories[key].total,
            }),
        ),
    ];

    const emptyMessage = unread
        ? category
            ? 'No unread notifications in this category.'
            : 'No unread notifications. You are all caught up.'
        : counts.total === 0
          ? 'Nothing here yet. New payments, requests and reminders will appear on this page.'
          : 'No notifications in this category.';

    return (
        <>
            <Head title="Notifications" />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Notifications
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {counts.unread > 0
                                ? `${counts.unread} unread of ${counts.total}`
                                : `${counts.total} in total — all read`}
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        disabled={counts.unread === 0}
                        onClick={() =>
                            router.post(
                                readAll.url(),
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <CheckCheck className="size-4" />
                        Mark all as read
                    </Button>
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <nav
                        aria-label="Notification categories"
                        className="flex flex-wrap gap-1"
                    >
                        {tabs.map((tab) => {
                            const active = (category ?? null) === tab.key;

                            return (
                                <Link
                                    key={tab.label}
                                    href={path}
                                    data={{
                                        category: tab.key ?? undefined,
                                        unread: unread ? 1 : undefined,
                                    }}
                                    preserveScroll
                                    aria-current={active ? 'page' : undefined}
                                    className={`flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                                        active
                                            ? 'bg-primary/15 text-foreground'
                                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                    }`}
                                >
                                    {tab.label}
                                    {tab.unread > 0 ? (
                                        <Badge
                                            variant="destructive"
                                            className="h-5 min-w-5 rounded-full px-1.5"
                                        >
                                            {tab.unread}
                                        </Badge>
                                    ) : (
                                        <span className="text-muted-foreground/70 text-xs">
                                            {tab.total}
                                        </span>
                                    )}
                                </Link>
                            );
                        })}
                    </nav>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="unread-only"
                            checked={unread}
                            onCheckedChange={(checked) =>
                                visit({
                                    category: category ?? undefined,
                                    unread: checked === true ? 1 : undefined,
                                })
                            }
                        />
                        <Label htmlFor="unread-only" className="text-sm">
                            Unread only
                        </Label>
                    </div>
                </div>

                <Card>
                    <CardContent className="p-0">
                        {list.data.length === 0 ? (
                            <div className="text-muted-foreground flex flex-col items-center gap-3 px-6 py-16 text-center text-sm">
                                <BellOff className="size-8 opacity-40" />
                                <p>{emptyMessage}</p>
                            </div>
                        ) : (
                            <ul className="divide-y">
                                {list.data.map((item) => (
                                    <li
                                        key={item.id}
                                        className={`flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-start ${item.read ? '' : 'bg-primary/5'}`}
                                    >
                                        <div className="flex min-w-0 flex-1 items-start gap-3">
                                            <NotificationIcon
                                                category={item.category}
                                            />
                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <h2
                                                        className={`text-sm ${item.read ? 'font-medium' : 'font-semibold'}`}
                                                    >
                                                        {item.title}
                                                    </h2>
                                                    {!item.read && (
                                                        <Badge variant="default">
                                                            New
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="text-muted-foreground mt-0.5 text-sm">
                                                    {item.body}
                                                </p>
                                                <p
                                                    className="text-muted-foreground/80 mt-1 text-xs"
                                                    title={formatDateTime(
                                                        item.created_at,
                                                    )}
                                                >
                                                    {
                                                        categoryMeta(
                                                            item.category,
                                                        ).label
                                                    }{' '}
                                                    · {timeAgo(item.created_at)}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="flex shrink-0 gap-2 sm:pl-3">
                                            {item.has_target && (
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Link href={item.open_url}>
                                                        View
                                                    </Link>
                                                </Button>
                                            )}
                                            {!item.read && (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        router.post(
                                                            read.url(item.id),
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Mark as read
                                                </Button>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                {list.total > 0 && (
                    <DataPagination
                        paginated={list}
                        onPageChange={(page) =>
                            visit({
                                category: category ?? undefined,
                                unread: unread ? 1 : undefined,
                                page,
                            })
                        }
                    />
                )}
            </div>
        </>
    );
}

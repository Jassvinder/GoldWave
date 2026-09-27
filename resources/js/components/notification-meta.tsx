import { Bell, CalendarClock, FileText, Wallet } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

type CategoryMeta = {
    label: string;
    icon: LucideIcon;
    /** Soft icon-circle colours, readable in light and dark mode. */
    tone: string;
};

/** Notification categories (`App\Notifications\AppNotification::category()`), in the order their tabs are shown. */
export const CATEGORY_ORDER = ['payment', 'request', 'emi', 'other'] as const;

export const CATEGORY_META: Record<string, CategoryMeta> = {
    payment: {
        label: 'Payments',
        icon: Wallet,
        tone: 'bg-green-500/15 text-green-700 dark:text-green-400',
    },
    request: {
        label: 'Requests',
        icon: FileText,
        tone: 'bg-blue-500/15 text-blue-700 dark:text-blue-400',
    },
    emi: {
        label: 'EMI Reminders',
        icon: CalendarClock,
        tone: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
    },
    other: {
        label: 'Other',
        icon: Bell,
        tone: 'bg-muted text-muted-foreground',
    },
};

export function categoryMeta(category: string): CategoryMeta {
    return CATEGORY_META[category] ?? CATEGORY_META.other;
}

/** The round category icon shown next to a notification. */
export function NotificationIcon({ category }: { category: string }) {
    const { icon: Icon, tone } = categoryMeta(category);

    return (
        <span
            className={`flex size-9 shrink-0 items-center justify-center rounded-full ${tone}`}
            aria-hidden="true"
        >
            <Icon className="size-4" />
        </span>
    );
}

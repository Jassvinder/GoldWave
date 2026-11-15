import { CalendarDays, Coins, Megaphone, Sparkles } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { iconBadgeColorClasses } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn, formatDate, formatRatePer10g } from '@/lib/utils';

export type TodaysRate = {
    metal: 'gold' | 'silver';
    rate_per_gram: string;
    effective_from: string | null;
};

export type NoticeKind = 'offer' | 'event' | 'notice';

export type DashboardNoticeItem = {
    id: number;
    kind: NoticeKind;
    title: string;
    body: string | null;
    ends_on: string | null;
};

export const NOTICE_KIND: Record<
    NoticeKind,
    { label: string; icon: LucideIcon; className: string }
> = {
    offer: {
        label: 'Offer',
        icon: Sparkles,
        className:
            'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
    },
    event: {
        label: 'Event',
        icon: CalendarDays,
        className:
            'border-blue-300 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-200',
    },
    notice: {
        label: 'Notice',
        icon: Megaphone,
        className:
            'border-slate-300 bg-slate-50 text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200',
    },
};

const METAL = {
    gold: { label: 'Gold', dot: 'bg-amber-400' },
    silver: { label: 'Silver', dot: 'bg-slate-400' },
} as const;

/**
 * T-245 (INSTRUCTIONS.md M01) — the first thing a member sees after login: today's gold and silver rate (Rate
 * Settings) and the offers, events and notices Super Admin or the company Admin put up. Renders nothing when there is
 * neither a rate nor a notice, so a fresh install shows no empty box.
 */
export function DashboardHighlights({
    rates,
    notices,
}: {
    rates: TodaysRate[];
    notices: DashboardNoticeItem[];
}) {
    if (rates.length === 0 && notices.length === 0) {
        return null;
    }

    return (
        <section
            aria-label="Today's rates and updates"
            className="grid grid-cols-1 gap-4 lg:grid-cols-3"
        >
            {rates.length > 0 && (
                <Card
                    className={cn(
                        'gap-3',
                        notices.length === 0 && 'lg:col-span-3',
                    )}
                >
                    <CardHeader className="flex flex-row items-center gap-3">
                        <span
                            className={cn(
                                'flex size-9 items-center justify-center rounded-lg',
                                iconBadgeColorClasses.amber,
                            )}
                        >
                            <Coins className="size-5" />
                        </span>
                        <CardTitle className="text-base">
                            Today&apos;s Rates
                        </CardTitle>
                    </CardHeader>
                    <CardContent
                        className={cn(
                            'grid gap-3',
                            notices.length === 0 && 'sm:grid-cols-2',
                        )}
                    >
                        {rates.map((rate) => (
                            <div
                                key={rate.metal}
                                className="flex items-center justify-between gap-3 rounded-md border px-3 py-2"
                            >
                                <div className="flex items-center gap-2">
                                    <span
                                        className={cn(
                                            'size-2.5 rounded-full',
                                            METAL[rate.metal].dot,
                                        )}
                                    />
                                    <span className="font-medium">
                                        {METAL[rate.metal].label}
                                    </span>
                                </div>
                                <div className="text-right">
                                    <div className="font-semibold tabular-nums">
                                        {formatRatePer10g(rate.rate_per_gram)}
                                    </div>
                                    <div className="text-muted-foreground text-xs">
                                        Rate from{' '}
                                        {formatDate(rate.effective_from)}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            )}

            {notices.length > 0 && (
                <Card
                    className={cn(
                        'gap-3',
                        rates.length > 0 ? 'lg:col-span-2' : 'lg:col-span-3',
                    )}
                >
                    <CardHeader className="flex flex-row items-center gap-3">
                        <span
                            className={cn(
                                'flex size-9 items-center justify-center rounded-lg',
                                iconBadgeColorClasses.purple,
                            )}
                        >
                            <Megaphone className="size-5" />
                        </span>
                        <CardTitle className="text-base">
                            Offers &amp; Updates
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="flex flex-col divide-y">
                            {notices.map((notice) => {
                                const kind = NOTICE_KIND[notice.kind];
                                const Icon = kind.icon;

                                return (
                                    <li
                                        key={notice.id}
                                        className="flex flex-col gap-1 py-3 first:pt-0 last:pb-0"
                                    >
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge
                                                variant="outline"
                                                className={kind.className}
                                            >
                                                <Icon className="size-3" />
                                                {kind.label}
                                            </Badge>
                                            <span className="font-medium">
                                                {notice.title}
                                            </span>
                                        </div>
                                        {notice.body && (
                                            <p className="text-muted-foreground text-sm whitespace-pre-line">
                                                {notice.body}
                                            </p>
                                        )}
                                        {notice.ends_on && (
                                            <p className="text-muted-foreground text-xs">
                                                Till{' '}
                                                {formatDate(notice.ends_on)}
                                            </p>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    </CardContent>
                </Card>
            )}
        </section>
    );
}

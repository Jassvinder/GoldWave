import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { ChevronRight, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

/** Shared icon-badge color tokens (T-100/T-103) — also used by `FormSection`. */
export const iconBadgeColorClasses = {
    blue: 'bg-blue-100 text-blue-600 dark:bg-blue-950 dark:text-blue-400',
    green: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400',
    amber: 'bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-400',
    purple: 'bg-purple-100 text-purple-600 dark:bg-purple-950 dark:text-purple-400',
    red: 'bg-rose-100 text-rose-600 dark:bg-rose-950 dark:text-rose-400',
    teal: 'bg-teal-100 text-teal-600 dark:bg-teal-950 dark:text-teal-400',
} as const;

const colorClasses = iconBadgeColorClasses;

export type StatCardColor = keyof typeof iconBadgeColorClasses;

export type StatCardProps = {
    icon: LucideIcon;
    color: StatCardColor;
    label: string;
    value: ReactNode;
    stats?: { label: string; value: ReactNode }[];
    href?: NonNullable<InertiaLinkProps['href']>;
    /** One plain-language line telling the reader what this number is. */
    description?: string;
    /** Itemised label/amount rows under the value, e.g. which incomes make up a total. `muted` greys out a zero row. */
    breakdown?: { label: string; value: ReactNode; muted?: boolean }[];
    /** Extra note below everything else, e.g. money earned but not yet credited. */
    footer?: ReactNode;
};

/**
 * Dashboard summary tile (T-100) — colored icon badge, label, big value, and
 * an optional sub-stats line, matching `Docs/Screenshots/2.png`'s pattern.
 * Used by all 3 portal dashboards (Member/Admin/Super Admin).
 */
export function StatCard({
    icon: Icon,
    color,
    label,
    value,
    stats,
    href,
    description,
    breakdown,
    footer,
}: StatCardProps) {
    return (
        <Card className="gap-3 p-4">
            <div className="flex items-start justify-between">
                <div
                    className={cn(
                        'flex size-10 shrink-0 items-center justify-center rounded-lg',
                        colorClasses[color],
                    )}
                >
                    <Icon className="size-5" />
                </div>
                {href && (
                    <Link
                        href={href}
                        className="text-muted-foreground hover:text-foreground"
                    >
                        <ChevronRight className="size-4" />
                    </Link>
                )}
            </div>

            <div className="flex flex-col gap-1">
                <span className="text-muted-foreground text-sm">{label}</span>
                <span className="text-2xl font-semibold">{value}</span>
                {description && (
                    <p className="text-muted-foreground text-xs">
                        {description}
                    </p>
                )}
            </div>

            {breakdown && breakdown.length > 0 && (
                <dl className="divide-border flex flex-col divide-y border-t text-sm">
                    {breakdown.map((row) => (
                        <div
                            key={row.label}
                            className={cn(
                                'flex items-center justify-between gap-3 py-1.5',
                                row.muted && 'text-muted-foreground',
                            )}
                        >
                            <dt>{row.label}</dt>
                            <dd className="font-medium tabular-nums">
                                {row.value}
                            </dd>
                        </div>
                    ))}
                </dl>
            )}

            {stats && stats.length > 0 && (
                <div className="text-muted-foreground flex flex-wrap gap-x-3 gap-y-0.5 text-xs">
                    {stats.map((stat) => (
                        <span key={stat.label}>
                            {stat.label}: {stat.value}
                        </span>
                    ))}
                </div>
            )}

            {footer}
        </Card>
    );
}

export function StatGrid({ children }: { children: ReactNode }) {
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {children}
        </div>
    );
}

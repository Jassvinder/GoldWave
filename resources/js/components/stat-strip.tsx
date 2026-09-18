import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

const dotClasses = {
    blue: 'bg-blue-500',
    green: 'bg-emerald-500',
    amber: 'bg-amber-500',
    purple: 'bg-purple-500',
    red: 'bg-rose-500',
    teal: 'bg-teal-500',
    muted: 'bg-muted-foreground',
} as const;

const badgeClasses = {
    blue: 'bg-blue-100 text-blue-600 dark:bg-blue-950 dark:text-blue-400',
    green: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400',
    amber: 'bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-400',
    purple: 'bg-purple-100 text-purple-600 dark:bg-purple-950 dark:text-purple-400',
    red: 'bg-rose-100 text-rose-600 dark:bg-rose-950 dark:text-rose-400',
    teal: 'bg-teal-100 text-teal-600 dark:bg-teal-950 dark:text-teal-400',
} as const;

export type StatStripColor = keyof typeof dotClasses;

/**
 * Slim horizontal summary row above a listing page's table (T-101) — a
 * headline total on the left, a row of colored-dot labeled counts, and an
 * optional trailing actions slot (e.g. an Export button), matching
 * `Docs/Screenshots/3.png`/`4.png`'s pattern. Distinct from the dashboard's
 * `StatCard`/`StatGrid` grid (`stat-card.tsx`), which is a different shape.
 */
export function StatStrip({
    icon: Icon,
    label,
    value,
    counts,
    actions,
}: {
    icon: LucideIcon;
    label: string;
    value: ReactNode;
    counts: { label: string; value: ReactNode; color: StatStripColor }[];
    actions?: ReactNode;
}) {
    return (
        <Card className="flex-row flex-wrap items-center gap-6 p-4">
            <div className="flex items-center gap-3">
                <div
                    className={cn(
                        'flex size-10 shrink-0 items-center justify-center rounded-lg',
                        badgeClasses.blue,
                    )}
                >
                    <Icon className="size-5" />
                </div>
                <div>
                    <div className="text-muted-foreground text-sm">{label}</div>
                    <div className="text-xl font-semibold">{value}</div>
                </div>
            </div>

            {counts.map((count) => (
                <div key={count.label} className="flex items-center gap-2">
                    <span
                        className={cn(
                            'size-2 shrink-0 rounded-full',
                            dotClasses[count.color],
                        )}
                    />
                    <span className="text-muted-foreground text-sm">
                        {count.label}
                    </span>
                    <span className="text-sm font-semibold">{count.value}</span>
                </div>
            ))}

            {actions && <div className="ml-auto shrink-0">{actions}</div>}
        </Card>
    );
}

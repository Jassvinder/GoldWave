import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type VersionTimelineEntry = {
    id: string | number;
    label: string;
    date: string;
    by?: string | null;
    note?: string | null;
    changes?: string[] | null;
    active?: boolean;
};

export type VersionTimelineProps = {
    entries: VersionTimelineEntry[];
    emptyMessage?: string;
};

/**
 * Version-history-timeline pattern (T-103) — colored dot + connecting line
 * per entry, matching `Docs/Screenshots/5.png`'s Version History panel.
 */
export function VersionTimeline({
    entries,
    emptyMessage = 'No history yet.',
}: VersionTimelineProps) {
    if (entries.length === 0) {
        return <p className="text-muted-foreground text-sm">{emptyMessage}</p>;
    }

    return (
        <div className="flex flex-col">
            {entries.map((entry, index) => (
                <div
                    key={entry.id}
                    className="relative flex gap-3 pb-6 last:pb-0"
                >
                    {index < entries.length - 1 && (
                        <span className="bg-border absolute top-3 left-[4px] h-full w-px" />
                    )}
                    <span
                        className={cn(
                            'relative z-10 mt-1.5 size-2.5 shrink-0 rounded-full',
                            entry.active
                                ? 'bg-primary'
                                : 'bg-muted-foreground/40',
                        )}
                    />
                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="font-medium">{entry.label}</span>
                            <span className="text-muted-foreground text-xs">
                                {entry.date}
                            </span>
                            {entry.active && (
                                <Badge className="h-5">Active</Badge>
                            )}
                        </div>
                        {entry.by && (
                            <div className="text-muted-foreground text-xs">
                                Published by {entry.by}
                            </div>
                        )}
                        {entry.note && (
                            <div className="text-muted-foreground text-xs">
                                {entry.note}
                            </div>
                        )}
                        {entry.changes && entry.changes.length > 0 && (
                            <ul className="mt-1 flex flex-col gap-0.5">
                                {entry.changes.map((change, index) => (
                                    <li key={index} className="text-xs">
                                        {change}
                                    </li>
                                ))}
                            </ul>
                        )}
                        {entry.changes && entry.changes.length === 0 && (
                            <div className="text-muted-foreground text-xs italic">
                                No settings changed in this version.
                            </div>
                        )}
                    </div>
                </div>
            ))}
        </div>
    );
}

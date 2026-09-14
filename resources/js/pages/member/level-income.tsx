import { Head } from '@inertiajs/react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDate } from '@/lib/utils';

type Row = {
    id: number;
    level_no: number;
    rate_percent: string;
    amount: string;
    source_customer_id: string | null;
    created_at: string;
};

type Props = { rows: Row[]; total: string };

/** INSTRUCTIONS.md M10 — 12-level Level Income history, source payment and amount per level (DOMAIN_LOGIC.md §6). */
export default function LevelIncome({ rows, total }: Props) {
    return (
        <>
            <Head title="Level Income" />

            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">Level Income</CardTitle>
                        <CardDescription>
                            Total earned: ₹{total}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {rows.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                No Level Income earned yet.
                            </p>
                        )}
                        {rows.map((row) => (
                            <div
                                key={row.id}
                                className="flex items-start justify-between gap-3 rounded-md border p-3"
                            >
                                <div className="min-w-0">
                                    <div className="font-medium">
                                        Level {row.level_no} ({row.rate_percent}
                                        %)
                                    </div>
                                    <div className="text-muted-foreground text-sm">
                                        From {row.source_customer_id ?? '—'} ·{' '}
                                        {formatDate(row.created_at)}
                                    </div>
                                </div>
                                <div className="shrink-0 font-medium whitespace-nowrap">
                                    ₹{row.amount}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

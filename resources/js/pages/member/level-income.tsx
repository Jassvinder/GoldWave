import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { formatDate } from '@/lib/utils';

type Row = {
    id: number;
    level_no: number;
    rate_percent: string;
    amount: string;
    source_customer_id: string | null;
    created_at: string;
    /** T-186 — set when the row was held for too few directs and paid later. */
    released_at: string | null;
};

type HeldRow = {
    id: number;
    level_no: number;
    rate_percent: string;
    amount: string;
    source_customer_id: string | null;
    created_at: string;
    required_directs: number;
};

type Props = {
    rows: Row[];
    total: string;
    held: HeldRow[];
    heldTotal: string;
    /** Only sent when something is held. */
    qualifiedDirects: number | null;
};

const levelCell = (row: { level_no: number; rate_percent: string }) => (
    <span className="font-medium">
        Level {row.level_no} ({row.rate_percent}%)
    </span>
);

const columns: DataTableColumn<Row>[] = [
    { key: 'level_no', header: 'Level', render: levelCell },
    {
        key: 'source_customer_id',
        header: 'From',
        render: (row) => row.source_customer_id ?? '—',
    },
    {
        key: 'created_at',
        header: 'Date',
        render: (row) =>
            row.released_at ? (
                <div className="flex flex-col">
                    <span>{formatDate(row.released_at)}</span>
                    <span className="text-muted-foreground text-xs">
                        Held from {formatDate(row.created_at)}
                    </span>
                </div>
            ) : (
                formatDate(row.created_at)
            ),
    },
    {
        key: 'amount',
        header: 'Amount',
        render: (row) => <span className="font-medium">₹{row.amount}</span>,
    },
];

function heldColumns(qualifiedDirects: number): DataTableColumn<HeldRow>[] {
    return [
        { key: 'level_no', header: 'Level', render: levelCell },
        {
            key: 'source_customer_id',
            header: 'From',
            render: (row) => row.source_customer_id ?? '—',
        },
        {
            key: 'created_at',
            header: 'Date',
            render: (row) => formatDate(row.created_at),
        },
        {
            key: 'required_directs',
            header: 'Needs',
            render: (row) => (
                <Badge variant="secondary">
                    {row.required_directs} directs (
                    {Math.max(row.required_directs - qualifiedDirects, 0)} more)
                </Badge>
            ),
        },
        {
            key: 'amount',
            header: 'Amount',
            render: (row) => <span className="font-medium">₹{row.amount}</span>,
        },
    ];
}

/**
 * INSTRUCTIONS.md M10 — 12-level Level Income history, source payment and amount per level (DOMAIN_LOGIC.md §6).
 * T-186 — income held for too few qualified directs is listed separately with the directs each level needs.
 */
export default function LevelIncome({
    rows,
    total,
    held,
    heldTotal,
    qualifiedDirects,
}: Props) {
    const directs = qualifiedDirects ?? 0;

    return (
        <>
            <Head title="Level Income" />

            <div className="flex w-full flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">Level Income</CardTitle>
                        <CardDescription>
                            Total earned: ₹{total}
                            {held.length > 0 && <> · On hold: ₹{heldTotal}</>}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            columns={columns}
                            rows={rows}
                            rowKey={(row) => row.id}
                            emptyMessage="No Level Income earned yet."
                        />
                    </CardContent>
                </Card>

                {held.length > 0 && (
                    <Card>
                        <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div className="flex flex-col gap-1.5">
                                <CardTitle>On hold</CardTitle>
                                <CardDescription>
                                    Each level needs a number of qualified
                                    direct members. You have {directs} now. Held
                                    income is added to your wallet as soon as
                                    you reach the number shown for its level.
                                </CardDescription>
                            </div>
                            {/* T-193 — the section's own total, so the member sees at a glance how much is waiting. */}
                            <div className="shrink-0 rounded-md bg-amber-50 px-4 py-2 text-amber-800 sm:text-right dark:bg-amber-950 dark:text-amber-300">
                                <div className="text-xs">Total on hold</div>
                                <div className="text-xl font-semibold tabular-nums">
                                    ₹
                                    {Number(heldTotal).toLocaleString('en-IN', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2,
                                    })}
                                </div>
                                <div className="text-xs">
                                    {held.length}{' '}
                                    {held.length === 1 ? 'entry' : 'entries'}
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <DataTable
                                columns={heldColumns(directs)}
                                rows={held}
                                rowKey={(row) => row.id}
                                emptyMessage="Nothing on hold."
                            />
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

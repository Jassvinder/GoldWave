import { Head } from '@inertiajs/react';
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
};

type Props = { rows: Row[]; total: string };

const columns: DataTableColumn<Row>[] = [
    {
        key: 'level_no',
        header: 'Level',
        render: (row) => (
            <span className="font-medium">
                Level {row.level_no} ({row.rate_percent}%)
            </span>
        ),
    },
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
        key: 'amount',
        header: 'Amount',
        render: (row) => <span className="font-medium">₹{row.amount}</span>,
    },
];

/** INSTRUCTIONS.md M10 — 12-level Level Income history, source payment and amount per level (DOMAIN_LOGIC.md §6). */
export default function LevelIncome({ rows, total }: Props) {
    return (
        <>
            <Head title="Level Income" />

            <div className="flex w-full flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">Level Income</CardTitle>
                        <CardDescription>
                            Total earned: ₹{total}
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
            </div>
        </>
    );
}

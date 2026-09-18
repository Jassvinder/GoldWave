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

type Entry = {
    id: number;
    entry_type: 'credit' | 'debit';
    category: string;
    amount: string;
    status: 'pending' | 'confirmed' | 'reversed';
    description: string | null;
    processed_at: string | null;
    created_at: string;
};

type Props = {
    wallet_balance: string;
    wallet_hold_amount: string;
    entries: Entry[];
};

const columns: DataTableColumn<Entry>[] = [
    {
        key: 'category',
        header: 'Category',
        render: (row) => (
            <span className="font-medium capitalize">
                {row.category.replace(/_/g, ' ')}
            </span>
        ),
    },
    {
        key: 'description',
        header: 'Description',
        render: (row) => row.description ?? '—',
    },
    {
        key: 'processed_at',
        header: 'Date',
        render: (row) => formatDate(row.processed_at ?? row.created_at),
    },
    {
        key: 'amount',
        header: 'Amount',
        render: (row) => (
            <span
                className={
                    row.entry_type === 'credit'
                        ? 'text-green-600 dark:text-green-400'
                        : 'text-destructive'
                }
            >
                {row.entry_type === 'credit' ? '+' : '-'}₹{row.amount}
            </span>
        ),
    },
    {
        key: 'status',
        header: 'Status',
        render: (row) => (
            <Badge
                variant={row.status === 'confirmed' ? 'default' : 'secondary'}
            >
                {row.status}
            </Badge>
        ),
    },
];

/** INSTRUCTIONS.md M14 — balance + full transaction ledger (DOMAIN_LOGIC.md §12). */
export default function Wallet({
    wallet_balance,
    wallet_hold_amount,
    entries,
}: Props) {
    return (
        <>
            <Head title="Wallet" />

            <div className="flex w-full flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">Wallet</CardTitle>
                        <CardDescription>
                            Balance ₹{wallet_balance} · On hold ₹
                            {wallet_hold_amount}
                        </CardDescription>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Ledger</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            columns={columns}
                            rows={entries}
                            rowKey={(row) => row.id}
                            emptyMessage="No wallet activity yet."
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

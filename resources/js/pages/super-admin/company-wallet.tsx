import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { formatDateTime } from '@/lib/utils';

type LedgerEntry = {
    id: number;
    entry_type: 'credit' | 'debit';
    category: string;
    amount: string;
    description: string;
    occurred_at: string;
};

type Props = { balance: string; entries: LedgerEntry[] };

const columns: DataTableColumn<LedgerEntry>[] = [
    {
        key: 'occurred_at',
        header: 'Date',
        render: (row) => formatDateTime(row.occurred_at),
    },
    {
        key: 'description',
        header: 'Description',
        render: (row) => row.description,
    },
    {
        key: 'entry_type',
        header: 'Type',
        render: (row) => (
            <Badge
                variant={row.entry_type === 'credit' ? 'default' : 'secondary'}
                className="capitalize"
            >
                {row.entry_type}
            </Badge>
        ),
    },
    {
        key: 'amount',
        header: 'Amount',
        render: (row) => `₹${row.amount}`,
    },
];

/** DOMAIN_LOGIC.md §12.2(b) — T-153. Credited whenever a Member/Store funds an Assisted Registration from their own wallet. */
export default function SuperAdminCompanyWallet({ balance, entries }: Props) {
    return (
        <>
            <Head title="Company Wallet" />

            <div className="flex w-full flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Company Wallet
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-3xl font-semibold">₹{balance}</p>
                        <p className="text-muted-foreground text-sm">
                            Credited when a Member or Store funds an Assisted
                            Registration from their own wallet balance
                            (DOMAIN_LOGIC.md §12.2(b)) — never touched by
                            Store-Wallet-Funded Cash Collection (§12.2(a)),
                            which just settles a store's already-paid
                            advance.
                        </p>
                    </CardContent>
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
                            emptyMessage="No Company Wallet activity yet."
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

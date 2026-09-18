import { Head, usePage } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { show } from '@/routes/super-admin/store-wallets';

type StoreRow = {
    id: number;
    name: string;
    advance_amount: string;
    wallet_balance: string;
    wallet_status: string | null;
};

type Props = { stores: StoreRow[] };

const columns: DataTableColumn<StoreRow>[] = [
    {
        key: 'name',
        header: 'Store Name',
        render: (row) => <span className="font-medium">{row.name}</span>,
    },
    {
        key: 'advance_amount',
        header: 'Advance',
        render: (row) => `₹${row.advance_amount}`,
    },
    {
        key: 'wallet_balance',
        header: 'Wallet Balance',
        render: (row) => `₹${row.wallet_balance}`,
    },
    {
        key: 'wallet_status',
        header: 'Status',
        render: (row) =>
            row.wallet_status ? (
                <Badge variant="secondary" className="capitalize">
                    {row.wallet_status}
                </Badge>
            ) : (
                '—'
            ),
    },
];

/** INSTRUCTIONS.md S10 — view/credit Store Wallets, advance balance, wallet transaction history. */
export default function SuperAdminStoreWalletManagement({ stores }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;

    return (
        <>
            <Head title="Store Wallet Management" />

            <div className="flex w-full flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Store Wallets
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            columns={columns}
                            rows={stores}
                            rowKey={(row) => row.id}
                            rowHref={(row) => show.url(row.id)}
                            emptyMessage="No stores yet."
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { formatDate } from '@/lib/utils';

type Payment = {
    id: number;
    type: 'registration' | 'emi_installment';
    installment_no: number | null;
    amount: string;
    mode: 'online' | 'cash';
    status: 'pending' | 'paid' | 'failed';
    provider_reference: string | null;
    paid_at: string | null;
};

type Props = { payments: Payment[] };

const STATUS_VARIANT: Record<
    Payment['status'],
    'default' | 'secondary' | 'destructive'
> = {
    pending: 'secondary',
    paid: 'default',
    failed: 'destructive',
};

const columns: DataTableColumn<Payment>[] = [
    {
        key: 'type',
        header: 'Payment',
        render: (row) => (
            <span className="font-medium">
                {row.type === 'registration'
                    ? 'Registration'
                    : `Installment #${row.installment_no}`}
            </span>
        ),
    },
    { key: 'amount', header: 'Amount', render: (row) => `₹${row.amount}` },
    {
        key: 'mode',
        header: 'Mode',
        render: (row) => <span className="capitalize">{row.mode}</span>,
    },
    {
        key: 'provider_reference',
        header: 'Reference',
        render: (row) => row.provider_reference ?? '—',
    },
    {
        key: 'paid_at',
        header: 'Paid',
        render: (row) => formatDate(row.paid_at),
    },
    {
        key: 'status',
        header: 'Status',
        render: (row) => (
            <Badge variant={STATUS_VARIANT[row.status]}>{row.status}</Badge>
        ),
    },
];

/** INSTRUCTIONS.md M07 — every Payment In transaction (registration + EMI installments). */
export default function PaymentHistory({ payments }: Props) {
    return (
        <>
            <Head title="Payment History" />

            <div className="flex w-full flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Payment History
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            columns={columns}
                            rows={payments}
                            rowKey={(row) => row.id}
                            emptyMessage="No payments yet."
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

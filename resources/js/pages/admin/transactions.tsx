import { Head } from '@inertiajs/react';
import { ClipboardList, Receipt } from 'lucide-react';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { FormSection } from '@/components/form-section';
import { Badge } from '@/components/ui/badge';
import { formatDate } from '@/lib/utils';

type Sale = {
    id: number;
    transaction_type: string;
    customer_id: string | null;
    item_name: string;
    item_weight: string | null;
    rate: string | null;
    total_invoice_amount: string;
    payment_source: string;
    store_wallet_deduction_reference: string | null;
    status: string;
    distribution_status: string;
    invoice_no: string | null;
    created_at: string | null;
};

type ActivityEntry = {
    action_type: string;
    operator_name: string | null;
    affected_customer_id: string | null;
    occurred_at: string | null;
};

type Props = { sales: Sale[]; activity: ActivityEntry[] };

/** INSTRUCTIONS.md A05 — transaction details + operational history (DOMAIN_LOGIC.md §16.3). */
export default function AdminTransactions({ sales, activity }: Props) {
    return (
        <>
            <Head title="Store Transactions" />

            <div className="flex w-full flex-col gap-6 p-4">
                <FormSection icon={Receipt} color="blue" title="Transactions">
                    <DataTable
                        columns={saleColumns}
                        rows={sales}
                        rowKey={(row) => row.id}
                        emptyMessage="No transactions yet."
                    />
                </FormSection>

                <FormSection
                    icon={ClipboardList}
                    color="purple"
                    title="Operational History"
                >
                    <DataTable
                        columns={activityColumns}
                        rows={activity}
                        rowKey={(row, index) => index}
                        emptyMessage="No activity recorded yet."
                    />
                </FormSection>
            </div>
        </>
    );
}

const saleColumns: DataTableColumn<Sale>[] = [
    {
        key: 'transaction_type',
        header: 'Transaction',
        render: (row) => (
            <span className="font-medium capitalize">
                {row.transaction_type.replace('_', ' ')} — {row.item_name}
            </span>
        ),
    },
    {
        key: 'customer_id',
        header: 'Customer',
        render: (row) => row.customer_id ?? 'Walk-in',
    },
    {
        key: 'total_invoice_amount',
        header: 'Amount',
        render: (row) => (
            <span>
                {row.item_weight ? `${row.item_weight}g · ` : ''}
                {row.rate ? `₹${row.rate}/g · ` : ''}₹{row.total_invoice_amount}{' '}
                · {row.payment_source}
                {row.store_wallet_deduction_reference
                    ? ` (ref ${row.store_wallet_deduction_reference})`
                    : ''}
            </span>
        ),
    },
    {
        key: 'invoice_no',
        header: 'Invoice',
        render: (row) => row.invoice_no ?? '—',
    },
    {
        key: 'created_at',
        header: 'Date',
        render: (row) => formatDate(row.created_at),
    },
    {
        key: 'status',
        header: 'Status',
        render: (row) => <Badge variant="secondary">{row.status}</Badge>,
    },
    {
        key: 'distribution_status',
        header: 'Distribution',
        render: (row) => (
            <Badge
                variant={
                    row.distribution_status === 'processed'
                        ? 'default'
                        : 'secondary'
                }
            >
                {row.distribution_status}
            </Badge>
        ),
    },
];

const activityColumns: DataTableColumn<ActivityEntry>[] = [
    {
        key: 'action_type',
        header: 'Action',
        render: (row) => (
            <span className="capitalize">
                {row.action_type.replace(/_/g, ' ')}
                {row.affected_customer_id
                    ? ` — ${row.affected_customer_id}`
                    : ''}
            </span>
        ),
    },
    {
        key: 'operator_name',
        header: 'By',
        render: (row) => row.operator_name ?? '—',
    },
    {
        key: 'occurred_at',
        header: 'Date',
        render: (row) => formatDate(row.occurred_at),
    },
];

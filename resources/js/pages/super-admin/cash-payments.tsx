import { Head, router } from '@inertiajs/react';
import { Banknote } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { StatStrip } from '@/components/stat-strip';
import * as cashPayments from '@/routes/super-admin/cash-payments';

type PendingPayment = {
    id: number;
    type: 'registration' | 'emi_installment';
    amount: string;
    created_at: string;
    member: {
        customer_id: string | null;
        user: { name: string; email: string } | null;
    } | null;
    emi_installment: { installment_no: number } | null;
};

type Props = {
    pending: PendingPayment[];
};

const columns: DataTableColumn<PendingPayment>[] = [
    {
        key: 'member',
        header: 'Member',
        render: (row) => (
            <div>
                <div className="font-medium">
                    {row.member?.user?.name ?? 'Unknown member'}
                </div>
                <div className="text-muted-foreground text-xs">
                    {row.member?.user?.email}
                </div>
            </div>
        ),
    },
    {
        key: 'type',
        header: 'Payment Type',
        render: (row) => (
            <Badge variant="secondary">
                {row.type === 'registration'
                    ? 'Registration'
                    : `EMI Installment #${row.emi_installment?.installment_no}`}
            </Badge>
        ),
    },
    { key: 'amount', header: 'Amount', render: (row) => `₹${row.amount}` },
];

/**
 * DOMAIN_LOGIC.md §3.1 step 8 / §10.2 — Super Admin confirms a member's cash
 * registration payment before their membership can activate.
 */
export default function CashPayments({ pending }: Props) {
    function approve(id: number) {
        router.post(cashPayments.approve.url(id), {}, { preserveScroll: true });
    }

    function reject(id: number) {
        router.post(cashPayments.reject.url(id), {}, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Cash Payments" />

            <div className="flex w-full flex-col gap-4 p-4">
                <StatStrip
                    icon={Banknote}
                    label="Pending Cash Payments"
                    value={pending.length}
                    counts={[]}
                />

                <DataTable
                    columns={columns}
                    rows={pending}
                    rowKey={(row) => row.id}
                    emptyMessage="No cash payments awaiting confirmation."
                    renderActions={(row) => (
                        <div className="flex justify-end gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => reject(row.id)}
                            >
                                Reject
                            </Button>
                            <Button size="sm" onClick={() => approve(row.id)}>
                                Approve
                            </Button>
                        </div>
                    )}
                />
            </div>
        </>
    );
}

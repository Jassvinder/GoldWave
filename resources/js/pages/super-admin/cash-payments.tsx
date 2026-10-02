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
    mode: 'cash' | 'upi';
    amount: string;
    created_at: string;
    member: {
        customer_id: string | null;
        user: { name: string; email: string } | null;
    } | null;
    emi_installment: { installment_no: number } | null;
    /** T-184 — set on a "Pay All Remaining EMIs" payment. */
    covers_installments: number | null;
    /** T-196 — GPay/UPI proof. */
    upi_reference: string | null;
    upi_screenshot_url: string | null;
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
                    {row.member?.customer_id ?? row.member?.user?.email}
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
                    : row.covers_installments !== null
                      ? `Full payment — ${row.covers_installments} EMIs`
                      : `EMI Installment #${row.emi_installment?.installment_no}`}
            </Badge>
        ),
    },
    { key: 'amount', header: 'Amount', render: (row) => `₹${row.amount}` },
    {
        key: 'mode',
        header: 'Paid by',
        render: (row) =>
            row.mode === 'upi' ? (
                <div className="flex flex-col gap-1">
                    <Badge variant="outline">GPay / UPI</Badge>
                    <span className="text-xs">
                        Ref ID:{' '}
                        <span className="font-mono font-medium">
                            {row.upi_reference ?? '—'}
                        </span>
                    </span>
                    {row.upi_screenshot_url ? (
                        <a
                            href={row.upi_screenshot_url}
                            target="_blank"
                            rel="noreferrer"
                            className="text-primary text-xs underline"
                        >
                            View screenshot
                        </a>
                    ) : (
                        <span className="text-muted-foreground text-xs">
                            No screenshot
                        </span>
                    )}
                </div>
            ) : (
                <Badge variant="outline">Cash</Badge>
            ),
    },
];

/**
 * DOMAIN_LOGIC.md §3.1 step 8 / §10.2 — Super Admin or Admin confirms a Cash or GPay/UPI payment (T-196) before a
 * registration activates or an EMI counts as paid. For GPay/UPI, check the Ref ID and screenshot against the bank
 * statement first.
 */
export default function CashPayments({ pending }: Props) {
    function approve(id: number) {
        router.post(cashPayments.approve.url(id), {}, { preserveScroll: true });
    }

    function reject(id: number) {
        router.post(cashPayments.reject.url(id), {}, { preserveScroll: true });
    }

    const upiCount = pending.filter((row) => row.mode === 'upi').length;

    return (
        <>
            <Head title="Payment Approvals" />

            <div className="flex w-full flex-col gap-4 p-4">
                <StatStrip
                    icon={Banknote}
                    label="Payments awaiting approval"
                    value={pending.length}
                    counts={[
                        {
                            label: 'Cash',
                            value: pending.length - upiCount,
                            color: 'amber',
                        },
                        { label: 'GPay / UPI', value: upiCount, color: 'blue' },
                    ]}
                />

                <p className="text-muted-foreground text-sm">
                    For GPay / UPI, match the Ref ID and screenshot with the
                    company bank statement before approving.
                </p>

                <DataTable
                    columns={columns}
                    rows={pending}
                    rowKey={(row) => row.id}
                    emptyMessage="No payments awaiting approval."
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

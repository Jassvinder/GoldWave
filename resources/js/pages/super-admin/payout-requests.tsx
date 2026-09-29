import { Head, router, usePage } from '@inertiajs/react';
import { History, Wallet } from 'lucide-react';
import { useState } from 'react';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { FormSection } from '@/components/form-section';
import { ProcessPayoutDialog } from '@/components/process-payout-dialog';
import { StatStrip } from '@/components/stat-strip';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDate } from '@/lib/utils';
import { cancel, reject } from '@/routes/super-admin/payout-requests';

type PendingPayout = {
    id: number;
    requested_amount: string;
    created_at: string | null;
    member: { customer_id: string | null; name: string | null };
    bank_detail: {
        account_holder_name: string | null;
        account_number: string | null;
        ifsc_code: string | null;
        bank_name: string | null;
        verified_at: string | null;
    } | null;
};

type HistoryStatus = 'processed' | 'rejected' | 'failed' | 'cancelled';

type HistoryPayout = {
    id: number;
    status: HistoryStatus;
    requested_amount: string;
    requested_at: string | null;
    decided_at: string | null;
    member: { customer_id: string | null; name: string | null };
    transaction: {
        method: string;
        reference: string | null;
        batch_reference: string | null;
        tds_amount: string;
        processing_fee: string;
        net_amount: string;
        bank_name: string | null;
        account_number: string | null;
        processed_by: string | null;
    } | null;
};

type Props = { pending: PendingPayout[]; history: HistoryPayout[] };

type HistoryFilter = 'all' | HistoryStatus;

const HISTORY_FILTERS: { value: HistoryFilter; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'processed', label: 'Processed' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'failed', label: 'Failed' },
    { value: 'cancelled', label: 'Cancelled' },
];

const STATUS_VARIANT: Record<
    HistoryStatus,
    'default' | 'secondary' | 'destructive'
> = {
    processed: 'default',
    rejected: 'destructive',
    failed: 'destructive',
    cancelled: 'secondary',
};

function memberCell(member: PendingPayout['member']) {
    return (
        <div>
            <div className="font-medium">{member.name ?? 'Unknown member'}</div>
            <div className="text-muted-foreground text-xs">
                {member.customer_id}
            </div>
        </div>
    );
}

const pendingColumns: DataTableColumn<PendingPayout>[] = [
    {
        key: 'member',
        header: 'Member',
        render: (row) => memberCell(row.member),
    },
    {
        key: 'requested_amount',
        header: 'Amount',
        render: (row) => `₹${row.requested_amount}`,
    },
    {
        key: 'bank_detail',
        header: 'Bank Account',
        render: (row) =>
            row.bank_detail ? (
                <div>
                    <div>
                        {row.bank_detail.bank_name} ·{' '}
                        {row.bank_detail.account_number}
                    </div>
                    <div className="text-muted-foreground text-xs">
                        {row.bank_detail.verified_at
                            ? `Verified ${formatDate(row.bank_detail.verified_at)}`
                            : 'Not verified'}
                    </div>
                </div>
            ) : (
                '—'
            ),
    },
    {
        key: 'created_at',
        header: 'Requested',
        render: (row) => formatDate(row.created_at),
    },
];

const historyColumns: DataTableColumn<HistoryPayout>[] = [
    {
        key: 'id',
        header: 'Request',
        render: (row) => <span className="font-medium">#{row.id}</span>,
    },
    {
        key: 'member',
        header: 'Member',
        render: (row) => memberCell(row.member),
    },
    {
        key: 'requested_amount',
        header: 'Amount',
        render: (row) => (
            <div>
                <div>₹{row.requested_amount}</div>
                {row.status === 'processed' && row.transaction && (
                    <div className="text-muted-foreground text-xs">
                        Net ₹{row.transaction.net_amount} · TDS ₹
                        {row.transaction.tds_amount} · Fee ₹
                        {row.transaction.processing_fee}
                    </div>
                )}
            </div>
        ),
    },
    {
        key: 'transaction',
        header: 'Paid Via',
        render: (row) =>
            row.transaction ? (
                <div>
                    <div>
                        {row.transaction.method}
                        {row.transaction.reference &&
                            ` · Ref ${row.transaction.reference}`}
                    </div>
                    <div className="text-muted-foreground text-xs">
                        {row.transaction.bank_name ?? 'Bank'}
                        {row.transaction.account_number &&
                            ` · ${row.transaction.account_number}`}
                        {row.transaction.batch_reference &&
                            ` · Batch ${row.transaction.batch_reference}`}
                    </div>
                </div>
            ) : (
                <span className="text-muted-foreground">—</span>
            ),
    },
    {
        key: 'requested_at',
        header: 'Requested',
        render: (row) => formatDate(row.requested_at),
    },
    {
        key: 'decided_at',
        header: 'Decided',
        render: (row) => (
            <div>
                <div>{formatDate(row.decided_at)}</div>
                {row.transaction?.processed_by && (
                    <div className="text-muted-foreground text-xs">
                        by {row.transaction.processed_by}
                    </div>
                )}
            </div>
        ),
    },
    {
        key: 'status',
        header: 'Status',
        render: (row) => (
            <Badge variant={STATUS_VARIANT[row.status]} className="capitalize">
                {row.status}
            </Badge>
        ),
    },
];

/**
 * T-109 (17-09-2026) — Super Admin's Payout Requests queue, wiring up
 * `ProcessPayoutRequest`/`FailPayoutRequest`/`RejectPayoutRequest` (T-009).
 * 28-09-2026 — adds Payout History below the queue: every request that has
 * left `pending`, with its transaction (method, reference, TDS/fee, net,
 * who processed it), filterable by outcome.
 */
export default function PayoutRequests({ pending, history }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const [filter, setFilter] = useState<HistoryFilter>('all');

    const countOf = (status: HistoryStatus) =>
        history.filter((row) => row.status === status).length;

    const visibleHistory =
        filter === 'all'
            ? history
            : history.filter((row) => row.status === filter);

    function rejectRequest(id: number) {
        router.post(reject.url(id), {}, { preserveScroll: true });
    }

    function cancelRequest(id: number) {
        router.post(cancel.url(id), {}, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Payout Requests" />

            <div className="flex w-full flex-col gap-4 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <StatStrip
                    icon={Wallet}
                    label="Pending Payout Requests"
                    value={pending.length}
                    counts={[
                        {
                            label: 'Processed',
                            value: countOf('processed'),
                            color: 'green',
                        },
                        {
                            label: 'Rejected',
                            value: countOf('rejected'),
                            color: 'red',
                        },
                        {
                            label: 'Failed',
                            value: countOf('failed'),
                            color: 'amber',
                        },
                        {
                            label: 'Cancelled',
                            value: countOf('cancelled'),
                            color: 'muted',
                        },
                    ]}
                />

                <DataTable
                    columns={pendingColumns}
                    rows={pending}
                    rowKey={(row) => row.id}
                    emptyMessage="No payout requests awaiting processing."
                    renderActions={(row) => (
                        <div className="flex justify-end gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => cancelRequest(row.id)}
                            >
                                Cancel
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => rejectRequest(row.id)}
                            >
                                Reject
                            </Button>
                            <ProcessPayoutDialog payoutRequest={row} />
                        </div>
                    )}
                />

                <FormSection
                    icon={History}
                    color="purple"
                    title="Payout History"
                    description="Every request that has left the queue — processed, rejected, failed or cancelled — newest decision first."
                    contentClassName="flex flex-col gap-4"
                >
                    <Tabs
                        value={filter}
                        onValueChange={(value) =>
                            setFilter(value as HistoryFilter)
                        }
                    >
                        <TabsList className="flex-wrap">
                            {HISTORY_FILTERS.map((option) => (
                                <TabsTrigger
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label} (
                                    {option.value === 'all'
                                        ? history.length
                                        : countOf(option.value)}
                                    )
                                </TabsTrigger>
                            ))}
                        </TabsList>
                    </Tabs>

                    <DataTable
                        columns={historyColumns}
                        rows={visibleHistory}
                        rowKey={(row) => row.id}
                        emptyMessage={
                            filter === 'all'
                                ? 'No payout has been decided yet.'
                                : `No ${filter} payouts.`
                        }
                    />
                </FormSection>
            </div>
        </>
    );
}

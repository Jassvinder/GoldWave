import { Head, router, usePage } from '@inertiajs/react';
import { Wallet } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { ProcessPayoutDialog } from '@/components/process-payout-dialog';
import { StatStrip } from '@/components/stat-strip';
import { formatDate } from '@/lib/utils';
import { reject } from '@/routes/super-admin/payout-requests';

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

type Props = { pending: PendingPayout[] };

const columns: DataTableColumn<PendingPayout>[] = [
    {
        key: 'member',
        header: 'Member',
        render: (row) => (
            <div>
                <div className="font-medium">
                    {row.member.name ?? 'Unknown member'}
                </div>
                <div className="text-muted-foreground text-xs">
                    {row.member.customer_id}
                </div>
            </div>
        ),
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
                            ? `Verified ${row.bank_detail.verified_at}`
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

/**
 * T-109 (17-09-2026) — Super Admin's Payout Requests queue, wiring up
 * `ProcessPayoutRequest`/`FailPayoutRequest`/`RejectPayoutRequest` (T-009),
 * which had no page at all until now.
 */
export default function PayoutRequests({ pending }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;

    function rejectRequest(id: number) {
        router.post(reject.url(id), {}, { preserveScroll: true });
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
                    counts={[]}
                />

                <DataTable
                    columns={columns}
                    rows={pending}
                    rowKey={(row) => row.id}
                    emptyMessage="No payout requests awaiting processing."
                    renderActions={(row) => (
                        <div className="flex justify-end gap-2">
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
            </div>
        </>
    );
}

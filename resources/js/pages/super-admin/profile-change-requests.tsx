import { Head, router, usePage } from '@inertiajs/react';
import { ClipboardEdit } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { RejectChangeRequestDialog } from '@/components/reject-change-request-dialog';
import { StatStrip } from '@/components/stat-strip';
import { formatDate } from '@/lib/utils';
import { approve } from '@/routes/super-admin/profile-change-requests';

type PendingChangeRequest = {
    id: number;
    field_name: string;
    old_value: string | null;
    new_value: string | null;
    reason: string | null;
    created_at: string | null;
    member: { customer_id: string | null; name: string | null };
};

type Props = { pending: PendingChangeRequest[] };

const columns: DataTableColumn<PendingChangeRequest>[] = [
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
        key: 'field_name',
        header: 'Field',
        render: (row) => row.field_name.replace(/_/g, ' '),
    },
    {
        key: 'old_value',
        header: 'Current Value',
        render: (row) => row.old_value ?? '—',
    },
    {
        key: 'new_value',
        header: 'Requested Value',
        render: (row) => row.new_value ?? '—',
    },
    {
        key: 'reason',
        header: 'Reason',
        render: (row) => row.reason ?? '—',
    },
    {
        key: 'created_at',
        header: 'Requested',
        render: (row) => formatDate(row.created_at),
    },
];

/**
 * T-109 (17-09-2026) — Super Admin's Profile Change Requests queue, wiring
 * up `Approve/RejectProfileChangeRequest` (T-012), which previously only
 * ever appeared read-only inside a Member's Recent Activity feed.
 */
export default function ProfileChangeRequests({ pending }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;

    function approveRequest(id: number) {
        router.post(approve.url(id), {}, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Profile Change Requests" />

            <div className="flex w-full flex-col gap-4 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <StatStrip
                    icon={ClipboardEdit}
                    label="Pending Change Requests"
                    value={pending.length}
                    counts={[]}
                />

                <DataTable
                    columns={columns}
                    rows={pending}
                    rowKey={(row) => row.id}
                    emptyMessage="No profile change requests awaiting review."
                    renderActions={(row) => (
                        <div className="flex justify-end gap-2">
                            <RejectChangeRequestDialog changeRequest={row} />
                            <Button
                                size="sm"
                                onClick={() => approveRequest(row.id)}
                            >
                                Approve
                            </Button>
                        </div>
                    )}
                />
            </div>
        </>
    );
}

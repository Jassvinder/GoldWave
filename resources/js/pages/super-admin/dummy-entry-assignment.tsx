import { Head, usePage } from '@inertiajs/react';
import { AssignDummyEntryDialog } from '@/components/assign-dummy-entry-dialog';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { formatDate } from '@/lib/utils';

type Dummy = {
    id: number;
    customer_id: string;
    placeholder_name: string | null;
    placement_side: string | null;
    generated_at: string | null;
};

type Props = { unassigned: Dummy[] };

/**
 * INSTRUCTIONS.md S05 — enter leader details into an available dummy entry.
 * T-107 (17-09-2026) — rebuilt as a direct per-row Edit action (fill in the
 * real leader's details, save, done) replacing the old select-a-row-then-
 * fill-a-separate-card-below flow, which read as a disconnected two-step
 * process rather than a direct edit.
 */
export default function SuperAdminDummyEntryAssignment({ unassigned }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;

    return (
        <>
            <Head title="Dummy Entry Assignment" />

            <div className="flex w-full flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Unassigned Dummy Entries
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            columns={dummyColumns}
                            rows={unassigned}
                            rowKey={(row) => row.id}
                            emptyMessage="No unassigned dummy entries."
                            renderActions={(row) => (
                                <AssignDummyEntryDialog dummy={row} />
                            )}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

const dummyColumns: DataTableColumn<Dummy>[] = [
    {
        key: 'customer_id',
        header: 'Customer ID',
        render: (row) => <span className="font-medium">{row.customer_id}</span>,
    },
    {
        key: 'placeholder_name',
        header: 'Placeholder Name',
        render: (row) => row.placeholder_name ?? '—',
    },
    {
        key: 'placement_side',
        header: 'Placement',
        render: (row) => row.placement_side ?? '—',
    },
    {
        key: 'generated_at',
        header: 'Generated',
        render: (row) => formatDate(row.generated_at),
    },
];

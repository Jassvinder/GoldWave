import { Head, router, usePage } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { formatDate } from '@/lib/utils';
import { send } from '@/routes/super-admin/restock-shipments';

type ShipmentRow = {
    id: number;
    store_name: string | null;
    item_name: string;
    metal: string;
    weight: string | null;
    value: string;
    status: 'owed' | 'sent' | 'received';
    sent_at: string | null;
    received_at: string | null;
    created_at: string;
};

type Props = { shipments: ShipmentRow[] };

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'destructive'> =
    {
        owed: 'destructive',
        sent: 'secondary',
        received: 'default',
    };

/** DOMAIN_LOGIC.md §16.12 — T-152. Every restock owed to a store, and the "mark sent" action. */
export default function SuperAdminRestockShipments({ shipments }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;

    const markSent = (id: number) => {
        router.post(send.url(id), {}, { preserveScroll: true });
    };

    const columns: DataTableColumn<ShipmentRow>[] = [
        {
            key: 'store_name',
            header: 'Store',
            render: (row) => (
                <span className="font-medium">{row.store_name ?? '—'}</span>
            ),
        },
        {
            key: 'item_name',
            header: 'Item',
            render: (row) => `${row.item_name} (${row.metal})`,
        },
        {
            key: 'value',
            header: 'Value',
            render: (row) => `₹${row.value}`,
        },
        {
            key: 'created_at',
            header: 'Owed since',
            render: (row) => formatDate(row.created_at),
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

    return (
        <>
            <Head title="Restock Shipments" />

            <div className="flex w-full flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Restock Shipments
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            columns={columns}
                            rows={shipments}
                            rowKey={(row) => row.id}
                            emptyMessage="No restock shipments — every store-delivered joining so far was paid directly to the delivering store."
                            renderActions={(row) =>
                                row.status === 'owed' ? (
                                    <Button
                                        size="sm"
                                        onClick={() => markSent(row.id)}
                                    >
                                        Mark Sent
                                    </Button>
                                ) : row.status === 'sent' ? (
                                    <span className="text-muted-foreground text-xs">
                                        Awaiting store confirmation
                                    </span>
                                ) : null
                            }
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

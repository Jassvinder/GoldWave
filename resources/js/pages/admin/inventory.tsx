import { Head, router } from '@inertiajs/react';
import { Package, RefreshCw, Truck } from 'lucide-react';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { FormSection } from '@/components/form-section';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/utils';
import { received as markRestockReceived } from '@/routes/admin/inventory/restock';

type Item = {
    id: number;
    item_name: string;
    metal: string;
    weight: string;
    quantity: number;
    price: string;
    description: string | null;
};

type Buyback = {
    item_name: string;
    metal: string;
    weight: string;
    quantity: number;
    price_paid: string;
    customer_id: string | null;
    occurred_at: string | null;
};

type PendingRestock = {
    id: number;
    item_name: string;
    metal: string;
    weight: string | null;
    value: string;
    status: 'owed' | 'sent';
};

type Props = {
    items: Item[];
    buybacks: Buyback[];
    pending_restocks: PendingRestock[];
};

/** INSTRUCTIONS.md A04 — products, stock, stock movements, inventory status. */
export default function AdminInventory({
    items,
    buybacks,
    pending_restocks,
}: Props) {
    const confirmReceived = (id: number) => {
        router.post(
            markRestockReceived.url(id),
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Inventory" />

            <div className="flex w-full flex-col gap-6 p-4">
                {pending_restocks.length > 0 && (
                    <FormSection
                        icon={Truck}
                        color="purple"
                        title="Restock Shipments"
                        description="Jewellery the company owes you back for a joining you delivered but were never paid for directly (DOMAIN_LOGIC.md §16.12)."
                    >
                        <ul className="flex flex-col gap-2">
                            {pending_restocks.map((r) => (
                                <li
                                    key={r.id}
                                    className="flex items-center justify-between rounded-md border p-2"
                                >
                                    <span className="text-sm">
                                        {r.item_name} · {r.metal}
                                        {r.weight ? ` · ${r.weight}g` : ''} · ₹
                                        {r.value}
                                    </span>
                                    <div className="flex items-center gap-2">
                                        <Badge
                                            variant={
                                                r.status === 'sent'
                                                    ? 'secondary'
                                                    : 'destructive'
                                            }
                                            className="capitalize"
                                        >
                                            {r.status}
                                        </Badge>
                                        {r.status === 'sent' && (
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    confirmReceived(r.id)
                                                }
                                            >
                                                Confirm Received
                                            </Button>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </FormSection>
                )}

                <FormSection icon={Package} color="blue" title="Current Stock">
                    <DataTable
                        columns={itemColumns}
                        rows={items}
                        rowKey={(row) => row.id}
                        emptyMessage="No inventory allocated yet."
                    />
                </FormSection>

                <FormSection
                    icon={RefreshCw}
                    color="amber"
                    title="Stock Movements — Item Buybacks"
                >
                    <DataTable
                        columns={buybackColumns}
                        rows={buybacks}
                        rowKey={(row, index) => index}
                        emptyMessage="No buybacks recorded yet."
                    />
                </FormSection>
            </div>
        </>
    );
}

const itemColumns: DataTableColumn<Item>[] = [
    {
        key: 'item_name',
        header: 'Item',
        render: (row) => <span className="font-medium">{row.item_name}</span>,
    },
    {
        key: 'metal',
        header: 'Metal / Weight',
        render: (row) => (
            <span className="capitalize">
                {row.metal} · {row.weight}g
            </span>
        ),
    },
    {
        key: 'price',
        header: 'Price/Unit',
        render: (row) => `₹${row.price}`,
    },
    {
        key: 'description',
        header: 'Description',
        render: (row) => row.description ?? '—',
    },
    {
        key: 'quantity',
        header: 'In Stock',
        render: (row) => <span className="font-medium">{row.quantity}</span>,
    },
];

const buybackColumns: DataTableColumn<Buyback>[] = [
    {
        key: 'item_name',
        header: 'Item',
        render: (row) => <span className="font-medium">{row.item_name}</span>,
    },
    {
        key: 'metal',
        header: 'Metal / Weight',
        render: (row) => (
            <span className="capitalize">
                {row.metal} · {row.weight}g × {row.quantity}
            </span>
        ),
    },
    {
        key: 'customer_id',
        header: 'From',
        render: (row) => row.customer_id ?? '—',
    },
    {
        key: 'occurred_at',
        header: 'Date',
        render: (row) => formatDate(row.occurred_at),
    },
    {
        key: 'price_paid',
        header: 'Price Paid',
        render: (row) => `₹${row.price_paid}`,
    },
];

import { Head } from '@inertiajs/react';
import { Package, RefreshCw } from 'lucide-react';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { FormSection } from '@/components/form-section';
import { formatDate } from '@/lib/utils';

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

type Props = { items: Item[]; buybacks: Buyback[] };

/** INSTRUCTIONS.md A04 — products, stock, stock movements, inventory status. */
export default function AdminInventory({ items, buybacks }: Props) {
    return (
        <>
            <Head title="Inventory" />

            <div className="flex w-full flex-col gap-6 p-4">
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

import { Head } from '@inertiajs/react';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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

            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Current Stock
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {items.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                No inventory allocated yet.
                            </p>
                        )}
                        {items.map((item) => (
                            <div
                                key={item.id}
                                className="flex items-start justify-between gap-3 rounded-md border p-3 text-sm"
                            >
                                <div className="min-w-0">
                                    <div className="font-medium">
                                        {item.item_name}
                                    </div>
                                    <div className="text-muted-foreground capitalize">
                                        {item.metal} · {item.weight}g · ₹
                                        {item.price}/unit
                                        {item.description
                                            ? ` · ${item.description}`
                                            : ''}
                                    </div>
                                </div>
                                <div className="shrink-0 font-medium whitespace-nowrap">
                                    {item.quantity} in stock
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Stock Movements — Item Buybacks</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {buybacks.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                No buybacks recorded yet.
                            </p>
                        )}
                        {buybacks.map((buyback, index) => (
                            <div
                                key={index}
                                className="flex items-start justify-between gap-3 rounded-md border p-3 text-sm"
                            >
                                <div className="min-w-0">
                                    <div className="font-medium">
                                        {buyback.item_name}
                                    </div>
                                    <div className="text-muted-foreground capitalize">
                                        {buyback.metal} · {buyback.weight}g ×{' '}
                                        {buyback.quantity} · from{' '}
                                        {buyback.customer_id ?? '—'} ·{' '}
                                        {formatDate(buyback.occurred_at)}
                                    </div>
                                </div>
                                <div className="shrink-0 font-medium whitespace-nowrap">
                                    ₹{buyback.price_paid}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

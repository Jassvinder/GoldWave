import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { buyback, delivery, store as storeSale } from '@/routes/admin/sales';

type InventoryItem = {
    id: number;
    item_name: string;
    metal: string;
    weight: string;
    quantity: number;
    price: string;
};

type Sale = {
    id: number;
    transaction_type: string;
    customer_id: string | null;
    item_name: string;
    total_invoice_amount: string;
    payment_source: string;
    distribution_status: string;
    invoice_no: string | null;
};

type Props = {
    inventory_items: InventoryItem[];
    recent_sales: Sale[];
};

/** INSTRUCTIONS.md A03 — new joining payments, repurchases/sales, Store Wallet payment source, invoices (DOMAIN_LOGIC.md §16.2/§16.7/§16.10). */
export default function AdminSales({ inventory_items, recent_sales }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;

    const saleForm = useForm({
        transaction_type: 'new_sale',
        customer_id: '',
        store_inventory_item_id: '',
        item_name: '',
        item_weight: '',
        quantity: '1',
        rate: '',
        sale_amount: '',
        gst_amount: '0',
        payment_source: 'cash',
    });

    const buybackForm = useForm({
        customer_id: '',
        item_name: '',
        metal: 'gold',
        weight: '',
        quantity: '1',
        description: '',
    });

    const deliveryForm = useForm({
        customer_id: '',
        sale_amount: '',
        gst_amount: '0',
    });

    const [selectedItem, setSelectedItem] = useState('');

    const submitSale: FormEventHandler = (e) => {
        e.preventDefault();
        saleForm.post(storeSale.url());
    };

    const submitBuyback: FormEventHandler = (e) => {
        e.preventDefault();
        buybackForm.post(buyback.url());
    };

    const submitDelivery: FormEventHandler = (e) => {
        e.preventDefault();
        deliveryForm.post(delivery.url());
    };

    return (
        <>
            <Head title="Repurchases / Sales" />

            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Record a Sale / Purchase / Repurchase
                        </CardTitle>
                        <CardDescription>
                            Select a tracked inventory item, or enter a
                            custom item name for an untracked sale.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submitSale}
                            className="flex flex-col gap-3"
                        >
                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-2">
                                    <Label>Transaction Type</Label>
                                    <Select
                                        value={saleForm.data.transaction_type}
                                        onValueChange={(v) =>
                                            saleForm.setData(
                                                'transaction_type',
                                                v,
                                            )
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="new_sale">
                                                New Sale
                                            </SelectItem>
                                            <SelectItem value="purchase">
                                                Purchase
                                            </SelectItem>
                                            <SelectItem value="repurchase">
                                                Repurchase
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grid gap-2">
                                    <Label>Customer ID (optional)</Label>
                                    <Input
                                        value={saleForm.data.customer_id}
                                        onChange={(e) =>
                                            saleForm.setData(
                                                'customer_id',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Leave blank for walk-in"
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label>Inventory Item (optional)</Label>
                                <Select
                                    value={selectedItem}
                                    onValueChange={(v) => {
                                        setSelectedItem(v);
                                        saleForm.setData(
                                            'store_inventory_item_id',
                                            v,
                                        );
                                    }}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Custom / untracked item" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {inventory_items.map((item) => (
                                            <SelectItem
                                                key={item.id}
                                                value={String(item.id)}
                                            >
                                                {item.item_name} (
                                                {item.quantity} in stock)
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {!selectedItem && (
                                <div className="grid gap-2">
                                    <Label>Item Name</Label>
                                    <Input
                                        value={saleForm.data.item_name}
                                        onChange={(e) =>
                                            saleForm.setData(
                                                'item_name',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    {saleForm.errors.item_name && (
                                        <p className="text-destructive text-sm">
                                            {saleForm.errors.item_name}
                                        </p>
                                    )}
                                </div>
                            )}

                            <div className="grid grid-cols-3 gap-3">
                                <div className="grid gap-2">
                                    <Label>Weight (g)</Label>
                                    <Input
                                        type="number"
                                        step="0.001"
                                        value={saleForm.data.item_weight}
                                        onChange={(e) =>
                                            saleForm.setData(
                                                'item_weight',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Quantity</Label>
                                    <Input
                                        type="number"
                                        min="1"
                                        value={saleForm.data.quantity}
                                        onChange={(e) =>
                                            saleForm.setData(
                                                'quantity',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Rate (₹/g)</Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        value={saleForm.data.rate}
                                        onChange={(e) =>
                                            saleForm.setData(
                                                'rate',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-3 gap-3">
                                <div className="grid gap-2">
                                    <Label>Sale Amount</Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        value={saleForm.data.sale_amount}
                                        onChange={(e) =>
                                            saleForm.setData(
                                                'sale_amount',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    {saleForm.errors.sale_amount && (
                                        <p className="text-destructive text-sm">
                                            {saleForm.errors.sale_amount}
                                        </p>
                                    )}
                                </div>
                                <div className="grid gap-2">
                                    <Label>GST Amount</Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        value={saleForm.data.gst_amount}
                                        onChange={(e) =>
                                            saleForm.setData(
                                                'gst_amount',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Payment Source</Label>
                                    <Select
                                        value={saleForm.data.payment_source}
                                        onValueChange={(v) =>
                                            saleForm.setData(
                                                'payment_source',
                                                v,
                                            )
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="cash">
                                                Cash
                                            </SelectItem>
                                            <SelectItem value="store_wallet">
                                                Store Wallet
                                            </SelectItem>
                                            <SelectItem value="other">
                                                Other
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <Button
                                type="submit"
                                disabled={saleForm.processing}
                                className="self-start"
                            >
                                Record Transaction &amp; Generate Invoice
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Item Buyback</CardTitle>
                        <CardDescription>
                            Buys an item back from a member at the current
                            market rate (DOMAIN_LOGIC.md §16.7).
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submitBuyback}
                            className="flex flex-col gap-3"
                        >
                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-2">
                                    <Label>Customer ID</Label>
                                    <Input
                                        value={buybackForm.data.customer_id}
                                        onChange={(e) =>
                                            buybackForm.setData(
                                                'customer_id',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    {buybackForm.errors.customer_id && (
                                        <p className="text-destructive text-sm">
                                            {buybackForm.errors.customer_id}
                                        </p>
                                    )}
                                </div>
                                <div className="grid gap-2">
                                    <Label>Item Name</Label>
                                    <Input
                                        value={buybackForm.data.item_name}
                                        onChange={(e) =>
                                            buybackForm.setData(
                                                'item_name',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-3 gap-3">
                                <div className="grid gap-2">
                                    <Label>Metal</Label>
                                    <Select
                                        value={buybackForm.data.metal}
                                        onValueChange={(v) =>
                                            buybackForm.setData('metal', v)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="gold">
                                                Gold
                                            </SelectItem>
                                            <SelectItem value="silver">
                                                Silver
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grid gap-2">
                                    <Label>Weight (g)</Label>
                                    <Input
                                        type="number"
                                        step="0.001"
                                        value={buybackForm.data.weight}
                                        onChange={(e) =>
                                            buybackForm.setData(
                                                'weight',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    {buybackForm.errors.metal && (
                                        <p className="text-destructive text-sm">
                                            {buybackForm.errors.metal}
                                        </p>
                                    )}
                                </div>
                                <div className="grid gap-2">
                                    <Label>Quantity</Label>
                                    <Input
                                        type="number"
                                        min="1"
                                        value={buybackForm.data.quantity}
                                        onChange={(e) =>
                                            buybackForm.setData(
                                                'quantity',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>
                            <Button
                                type="submit"
                                disabled={buybackForm.processing}
                                variant="outline"
                                className="self-start"
                            >
                                Record Buyback
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Plan Jewellery Delivery</CardTitle>
                        <CardDescription>
                            Marks a new member's plan jewellery entitlement
                            delivered through this store (DOMAIN_LOGIC.md
                            §16.10).
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submitDelivery}
                            className="flex flex-col gap-3"
                        >
                            <div className="grid grid-cols-3 gap-3">
                                <div className="grid gap-2">
                                    <Label>Customer ID</Label>
                                    <Input
                                        value={deliveryForm.data.customer_id}
                                        onChange={(e) =>
                                            deliveryForm.setData(
                                                'customer_id',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    {deliveryForm.errors.customer_id && (
                                        <p className="text-destructive text-sm">
                                            {deliveryForm.errors.customer_id}
                                        </p>
                                    )}
                                </div>
                                <div className="grid gap-2">
                                    <Label>Sale Amount</Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        value={deliveryForm.data.sale_amount}
                                        onChange={(e) =>
                                            deliveryForm.setData(
                                                'sale_amount',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label>GST Amount</Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        value={deliveryForm.data.gst_amount}
                                        onChange={(e) =>
                                            deliveryForm.setData(
                                                'gst_amount',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>
                            <Button
                                type="submit"
                                disabled={deliveryForm.processing}
                                variant="outline"
                                className="self-start"
                            >
                                Record Delivery
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Recent Transactions</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {recent_sales.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                No transactions yet.
                            </p>
                        )}
                        {recent_sales.map((sale) => (
                            <div
                                key={sale.id}
                                className="flex items-center justify-between rounded-md border p-3 text-sm"
                            >
                                <div>
                                    <div className="font-medium capitalize">
                                        {sale.transaction_type.replace(
                                            '_',
                                            ' ',
                                        )}{' '}
                                        — {sale.item_name}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {sale.customer_id ?? 'Walk-in'} · ₹
                                        {sale.total_invoice_amount} ·{' '}
                                        {sale.payment_source} ·{' '}
                                        {sale.invoice_no}
                                    </div>
                                </div>
                                <Badge
                                    variant={
                                        sale.distribution_status ===
                                        'processed'
                                            ? 'default'
                                            : 'secondary'
                                    }
                                >
                                    {sale.distribution_status}
                                </Badge>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

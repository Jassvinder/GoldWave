import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Gem, RefreshCw, ShoppingBag, Wallet } from 'lucide-react';
import { FormEventHandler, useState } from 'react';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { FormSection } from '@/components/form-section';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    buyback,
    collectPayment,
    delivery,
    index as salesIndex,
    store as storeSale,
} from '@/routes/admin/sales';

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

type PendingPayment = {
    id: number;
    type: string;
    amount: string;
    created_at: string;
};

type CollectSearchResult = {
    customer_id: string;
    found: boolean;
    member_name?: string;
    payments: PendingPayment[];
} | null;

type Props = {
    inventory_items: InventoryItem[];
    recent_sales: Sale[];
    collect_search: CollectSearchResult;
};

/** INSTRUCTIONS.md A03 — new joining payments, repurchases/sales, Store Wallet payment source, invoices (DOMAIN_LOGIC.md §16.2/§16.7/§16.10). */
export default function AdminSales({
    inventory_items,
    recent_sales,
    collect_search,
}: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const [collectCustomerId, setCollectCustomerId] = useState(
        collect_search?.customer_id ?? '',
    );

    const searchCollect: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(
            salesIndex.url(),
            { collect_customer_id: collectCustomerId },
            { preserveState: true },
        );
    };

    const collect = (paymentId: number) => {
        router.post(collectPayment.url(paymentId), {}, { preserveScroll: true });
    };

    const saleForm = useForm({
        transaction_type: 'purchase',
        customer_id: '',
        store_inventory_item_id: '',
        item_name: '',
        metal: '',
        item_weight: '',
        quantity: '1',
        rate: '',
        sale_amount: '',
        gst_amount: '0',
        payment_source: 'cash',
    });

    const buybackForm = useForm({
        customer_id: '',
        walk_in_name: '',
        walk_in_mobile: '',
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

            <div className="flex w-full flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <FormSection
                    icon={Wallet}
                    color="green"
                    title="Collect a Pending Cash Payment"
                    description="A member paying you cash for their registration or an EMI installment — settle it instantly from this store's own Store Wallet (DOMAIN_LOGIC.md §12.2)."
                >
                    <form
                        onSubmit={searchCollect}
                        className="flex items-end gap-2"
                    >
                        <div className="grid gap-2">
                            <Label>Customer ID</Label>
                            <Input
                                value={collectCustomerId}
                                onChange={(e) =>
                                    setCollectCustomerId(e.target.value)
                                }
                                placeholder="e.g. GWL00123"
                            />
                        </div>
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
                    </form>

                    {collect_search && !collect_search.found && (
                        <p className="text-muted-foreground mt-3 text-sm">
                            No member found with Customer ID "
                            {collect_search.customer_id}".
                        </p>
                    )}

                    {collect_search?.found && (
                        <div className="mt-3">
                            <p className="mb-2 text-sm font-medium">
                                {collect_search.member_name} (
                                {collect_search.customer_id})
                            </p>
                            {collect_search.payments.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    No pending cash payments for this member.
                                </p>
                            ) : (
                                <ul className="flex flex-col gap-2">
                                    {collect_search.payments.map((p) => (
                                        <li
                                            key={p.id}
                                            className="flex items-center justify-between rounded-md border p-2"
                                        >
                                            <span className="text-sm">
                                                {p.type === 'registration'
                                                    ? 'Registration'
                                                    : 'EMI Installment'}{' '}
                                                — ₹{p.amount}
                                            </span>
                                            <Button
                                                size="sm"
                                                onClick={() => collect(p.id)}
                                            >
                                                Collect via Store Wallet
                                            </Button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    )}
                </FormSection>

                <FormSection
                    icon={ShoppingBag}
                    color="blue"
                    title="Record a Purchase / Repurchase"
                    description="Purchase covers both a member's and a walk-in (non-member) customer's buy — leave Customer ID blank for a walk-in. Repurchase always needs an existing member. Select a tracked inventory item, or enter a custom item name for an untracked sale."
                >
                    <form onSubmit={submitSale} className="flex flex-col gap-3">
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label>Transaction Type</Label>
                                <Select
                                    value={saleForm.data.transaction_type}
                                    onValueChange={(v) =>
                                        saleForm.setData('transaction_type', v)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="purchase">
                                            Purchase (member or walk-in)
                                        </SelectItem>
                                        <SelectItem value="repurchase">
                                            Repurchase (member only)
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label>
                                    Customer ID
                                    {saleForm.data.transaction_type ===
                                    'repurchase'
                                        ? ''
                                        : ' (optional)'}
                                </Label>
                                <Input
                                    value={saleForm.data.customer_id}
                                    onChange={(e) =>
                                        saleForm.setData(
                                            'customer_id',
                                            e.target.value,
                                        )
                                    }
                                    placeholder={
                                        saleForm.data.transaction_type ===
                                        'repurchase'
                                            ? 'Required for a repurchase'
                                            : 'Leave blank for walk-in'
                                    }
                                />
                                {saleForm.errors.customer_id && (
                                    <p className="text-destructive text-sm">
                                        {saleForm.errors.customer_id}
                                    </p>
                                )}
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
                                            {item.item_name} ({item.quantity} in
                                            stock)
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

                        {!selectedItem && (
                            <div className="grid gap-2">
                                <Label>Metal</Label>
                                <Select
                                    value={saleForm.data.metal}
                                    onValueChange={(v) =>
                                        saleForm.setData('metal', v)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select metal" />
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
                                {saleForm.errors.metal && (
                                    <p className="text-destructive text-sm">
                                        {saleForm.errors.metal}
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
                                        saleForm.setData('rate', e.target.value)
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
                                        saleForm.setData('payment_source', v)
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
                </FormSection>

                <FormSection
                    icon={RefreshCw}
                    color="amber"
                    title="Item Buyback"
                    description="Buys back old jewellery at the current market rate (DOMAIN_LOGIC.md §16.7) — usually from a non-member walk-in, but a member's Customer ID also works. Give either a Customer ID or a walk-in name, not both."
                >
                    <form
                        onSubmit={submitBuyback}
                        className="flex flex-col gap-3"
                    >
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label>Customer ID (member, optional)</Label>
                                <Input
                                    value={buybackForm.data.customer_id}
                                    onChange={(e) =>
                                        buybackForm.setData(
                                            'customer_id',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Leave blank for a walk-in seller"
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
                        {!buybackForm.data.customer_id && (
                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-2">
                                    <Label>Walk-in Seller Name</Label>
                                    <Input
                                        value={buybackForm.data.walk_in_name}
                                        onChange={(e) =>
                                            buybackForm.setData(
                                                'walk_in_name',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    {buybackForm.errors.walk_in_name && (
                                        <p className="text-destructive text-sm">
                                            {buybackForm.errors.walk_in_name}
                                        </p>
                                    )}
                                </div>
                                <div className="grid gap-2">
                                    <Label>Walk-in Mobile (optional)</Label>
                                    <Input
                                        value={buybackForm.data.walk_in_mobile}
                                        onChange={(e) =>
                                            buybackForm.setData(
                                                'walk_in_mobile',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>
                        )}
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
                </FormSection>

                <FormSection
                    icon={Gem}
                    color="purple"
                    title="Plan Jewellery Delivery"
                    description="Marks a new member's plan jewellery entitlement delivered through this store (DOMAIN_LOGIC.md §16.10)."
                >
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
                </FormSection>

                <FormSection
                    icon={ShoppingBag}
                    color="green"
                    title="Recent Transactions"
                >
                    <DataTable
                        columns={saleColumns}
                        rows={recent_sales}
                        rowKey={(row) => row.id}
                        emptyMessage="No transactions yet."
                    />
                </FormSection>
            </div>
        </>
    );
}

const saleColumns: DataTableColumn<Sale>[] = [
    {
        key: 'transaction_type',
        header: 'Transaction',
        render: (row) => (
            <span className="font-medium capitalize">
                {row.transaction_type.replace('_', ' ')} — {row.item_name}
            </span>
        ),
    },
    {
        key: 'customer_id',
        header: 'Customer',
        render: (row) =>
            row.customer_id ? (
                <span>{row.customer_id}</span>
            ) : (
                <Badge variant="secondary">Walk-in</Badge>
            ),
    },
    {
        key: 'total_invoice_amount',
        header: 'Amount',
        render: (row) => `₹${row.total_invoice_amount} · ${row.payment_source}`,
    },
    {
        key: 'invoice_no',
        header: 'Invoice',
        render: (row) => row.invoice_no ?? '—',
    },
    {
        key: 'distribution_status',
        header: 'Distribution',
        render: (row) => (
            <Badge
                variant={
                    row.distribution_status === 'processed'
                        ? 'default'
                        : 'secondary'
                }
            >
                {row.distribution_status}
            </Badge>
        ),
    },
];

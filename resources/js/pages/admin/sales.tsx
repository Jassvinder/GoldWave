import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FileText, Gem, RefreshCw, ShoppingBag, Wallet } from 'lucide-react';
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
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    HallmarkFields,
    type HallmarkPiece,
} from '@/components/hallmark-fields';
import { formatRatePer10g } from '@/lib/utils';
import {
    bill as generateBill,
    buyback,
    collectPayment,
    delivery,
    index as salesIndex,
    invoice as showInvoice,
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
    quantity: number;
    total_invoice_amount: string;
    payment_source: string;
    distribution_status: string;
    invoice_no: string | null;
};

/** T-171 — "Generate bill" for a sale, with optional hallmarking (one HUID + charge per piece). */
function GenerateBillDialog({ sale }: { sale: Sale }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ hallmarked: boolean; hallmarks: HallmarkPiece[] }>({
        hallmarked: false,
        hallmarks: [],
    });
    const errors = form.errors as Record<string, string | undefined>;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(generateBill.url(sale.id));
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <FileText />
                    Generate bill
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Generate bill</DialogTitle>
                    <DialogDescription>
                        {sale.item_name} × {sale.quantity}. The bill number is
                        fixed once generated — later prints are duplicate copies
                        of the same bill.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <HallmarkFields
                        idPrefix={`bill_${sale.id}`}
                        quantity={sale.quantity}
                        hallmarked={form.data.hallmarked}
                        pieces={form.data.hallmarks}
                        onHallmarkedChange={(value) =>
                            form.setData('hallmarked', value)
                        }
                        onPiecesChange={(pieces) =>
                            form.setData('hallmarks', pieces)
                        }
                        errors={errors}
                    />
                    {errors.bill && (
                        <p className="text-destructive text-sm">
                            {errors.bill}
                        </p>
                    )}
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Generate bill
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

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

type MetalPricing = { rate_per_gram: string; making_charge_percent: string };

/** T-169 — today's rate per metal (null when none was ever set) and the Super Admin GST %. */
type Pricing = {
    gold: MetalPricing | null;
    silver: MetalPricing | null;
    gst_percent: number;
};

type Props = {
    inventory_items: InventoryItem[];
    recent_sales: Sale[];
    collect_search: CollectSearchResult;
    pricing: Pricing;
};

type SalePrice =
    | { kind: 'incomplete' }
    | { kind: 'no_rate'; metal: string }
    | {
          kind: 'ready';
          ratePerGram: number;
          weight: number;
          quantity: number;
          metalValue: number;
          makingPercent: number;
          making: number;
          subtotal: number;
          gstPercent: number;
          gst: number;
          total: number;
      };

const round2 = (value: number) =>
    Math.round((value + Number.EPSILON) * 100) / 100;

const inr = (value: number) =>
    `₹${value.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

/** Mirrors `PriceStoreSale` on the server (which is what is actually charged). */
function previewSalePrice(
    pricing: Pricing,
    metal: string,
    weight: number,
    quantity: number,
): SalePrice {
    if (
        (metal !== 'gold' && metal !== 'silver') ||
        !(weight > 0) ||
        !(quantity >= 1)
    ) {
        return { kind: 'incomplete' };
    }

    const rate = pricing[metal];

    if (!rate) {
        return { kind: 'no_rate', metal };
    }

    const ratePerGram = Number(rate.rate_per_gram);
    const makingPercent = Number(rate.making_charge_percent);
    const metalValue = round2(weight * quantity * ratePerGram);
    const making = round2((metalValue * makingPercent) / 100);
    const subtotal = round2(metalValue + making);
    const gst = round2((subtotal * pricing.gst_percent) / 100);

    return {
        kind: 'ready',
        ratePerGram,
        weight,
        quantity,
        metalValue,
        makingPercent,
        making,
        subtotal,
        gstPercent: pricing.gst_percent,
        gst,
        total: round2(subtotal + gst),
    };
}

function SalePricePreview({ price }: { price: SalePrice }) {
    if (price.kind === 'incomplete') {
        return (
            <p className="text-muted-foreground rounded-md border border-dashed p-3 text-sm">
                Choose an item (or enter metal and weight) and quantity — the
                price is calculated automatically at today&apos;s rate.
            </p>
        );
    }

    if (price.kind === 'no_rate') {
        return (
            <p className="text-destructive rounded-md border border-dashed p-3 text-sm">
                No {price.metal} rate has been set yet — ask Super Admin to
                enter today&apos;s rate before selling.
            </p>
        );
    }

    const rows: [string, string][] = [
        [
            `Metal (${price.weight} g × ${price.quantity} at ${formatRatePer10g(price.ratePerGram)})`,
            inr(price.metalValue),
        ],
        [`Making charges (${price.makingPercent}%)`, inr(price.making)],
        ['Subtotal', inr(price.subtotal)],
        [`GST (${price.gstPercent}%)`, inr(price.gst)],
    ];

    return (
        <div className="bg-muted/40 rounded-md border p-3 text-sm">
            <dl className="flex flex-col gap-1">
                {rows.map(([label, value]) => (
                    <div key={label} className="flex justify-between gap-4">
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd className="tabular-nums">{value}</dd>
                    </div>
                ))}
                <div className="mt-1 flex justify-between gap-4 border-t pt-2 font-semibold">
                    <dt>Total</dt>
                    <dd className="tabular-nums">{inr(price.total)}</dd>
                </div>
            </dl>
            <p className="text-muted-foreground mt-2 text-xs">
                Calculated automatically at today&apos;s rate — it cannot be
                changed.
            </p>
        </div>
    );
}

/** INSTRUCTIONS.md A03 — new joining payments, repurchases/sales (paid Cash/Other — never from the Store Wallet, T-161), invoices (DOMAIN_LOGIC.md §16.2/§16.7/§16.10). */
export default function AdminSales({
    inventory_items,
    recent_sales,
    collect_search,
    pricing,
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
        router.post(
            collectPayment.url(paymentId),
            {},
            { preserveScroll: true },
        );
    };

    const saleForm = useForm({
        transaction_type: 'purchase',
        customer_id: '',
        store_inventory_item_id: '',
        item_name: '',
        metal: '',
        item_weight: '',
        quantity: '1',
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
        store_inventory_item_id: '',
    });

    const [selectedItem, setSelectedItem] = useState('');

    // T-169 — the price the server will charge, previewed live; the store cannot change it.
    const item = inventory_items.find((row) => String(row.id) === selectedItem);
    const salePrice = previewSalePrice(
        pricing,
        item ? item.metal : saleForm.data.metal,
        item ? Number(item.weight) : Number(saleForm.data.item_weight),
        Number(saleForm.data.quantity),
    );

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

                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            {!selectedItem && (
                                <div className="grid gap-2">
                                    <Label>Weight per piece (g)</Label>
                                    <Input
                                        type="number"
                                        step="0.001"
                                        min="0.001"
                                        value={saleForm.data.item_weight}
                                        onChange={(e) =>
                                            saleForm.setData(
                                                'item_weight',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    {saleForm.errors.item_weight && (
                                        <p className="text-destructive text-sm">
                                            {saleForm.errors.item_weight}
                                        </p>
                                    )}
                                </div>
                            )}
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
                                {saleForm.errors.quantity && (
                                    <p className="text-destructive text-sm">
                                        {saleForm.errors.quantity}
                                    </p>
                                )}
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
                                        <SelectItem value="other">
                                            Other
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <SalePricePreview price={salePrice} />

                        <Button
                            type="submit"
                            disabled={
                                saleForm.processing ||
                                salePrice.kind !== 'ready'
                            }
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
                    description="Hands a member's plan jewellery over from this store's stock (DOMAIN_LOGIC.md §16.10). The piece's stock goes down, and it is priced like a purchase: at the member's locked rate if they booked at the Current Rate, otherwise at today's rate, plus today's making % and GST."
                >
                    <form
                        onSubmit={submitDelivery}
                        className="flex flex-col gap-3"
                    >
                        <div className="grid gap-3 sm:grid-cols-2">
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
                                <Label>Item from your stock</Label>
                                <Select
                                    value={
                                        deliveryForm.data
                                            .store_inventory_item_id
                                    }
                                    onValueChange={(v) =>
                                        deliveryForm.setData(
                                            'store_inventory_item_id',
                                            v,
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Choose the piece being handed over" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {inventory_items.map((item) => (
                                            <SelectItem
                                                key={item.id}
                                                value={String(item.id)}
                                            >
                                                {item.item_name} — {item.metal},{' '}
                                                {item.weight} g ({item.quantity}{' '}
                                                in stock)
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {deliveryForm.errors
                                    .store_inventory_item_id && (
                                    <p className="text-destructive text-sm">
                                        {
                                            deliveryForm.errors
                                                .store_inventory_item_id
                                        }
                                    </p>
                                )}
                                {inventory_items.length === 0 && (
                                    <p className="text-muted-foreground text-xs">
                                        No stock in this store — ask Super Admin
                                        to allocate inventory first.
                                    </p>
                                )}
                            </div>
                        </div>
                        <Button
                            type="submit"
                            disabled={
                                deliveryForm.processing ||
                                !deliveryForm.data.store_inventory_item_id
                            }
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
        // T-160 — opens the printable invoice (Print / Share via WhatsApp). T-171 — a bill exists only once
        // generated; afterwards every copy is a duplicate with the same number.
        render: (row) =>
            row.invoice_no ? (
                <Link
                    href={showInvoice.url(row.id)}
                    className="text-primary inline-flex items-center gap-1 font-medium underline-offset-4 hover:underline"
                >
                    <FileText className="size-4" />
                    {row.invoice_no}
                </Link>
            ) : (
                <GenerateBillDialog sale={row} />
            ),
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

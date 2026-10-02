import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FileText, Gem, Search } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { FormSection } from '@/components/form-section';
import {
    HallmarkFields,
    type HallmarkPiece,
} from '@/components/hallmark-fields';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate, formatRatePer10g } from '@/lib/utils';
import {
    index,
    invoice as deliveryInvoice,
    store as recordDelivery,
} from '@/routes/super-admin/company-deliveries';

type Lookup =
    | { customer_id: string; found: false }
    | {
          customer_id: string;
          found: true;
          name: string | null;
          plan: string | null;
          metal: 'gold' | 'silver' | null;
          booking: 'current_rate' | 'future_rate' | 'one_time';
          entitled_weight_grams: string | null;
          commitment_amount: string | null;
          rate_per_gram: string | null;
          rate_is_locked: boolean;
          making_charge_percent: string | null;
          blocked_reason: string | null;
      };

type Delivery = {
    id: number;
    member: { customer_id: string | null; name: string | null };
    item_name: string;
    metal: string;
    weight: string;
    total_invoice_amount: string;
    delivered_at: string | null;
    invoice_no: string | null;
};

type Props = {
    deliveries: Delivery[];
    lookup: Lookup | null;
    gst_percent: number;
};

const inr = (value: number) =>
    `₹${value.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const round2 = (value: number) =>
    Math.round((value + Number.EPSILON) * 100) / 100;

const BOOKING_LABEL = {
    current_rate: 'Current Rate (rate locked at booking)',
    future_rate: 'Future Rate (today’s rate applies)',
    one_time: 'One-time plan (today’s rate applies)',
};

const deliveryColumns: DataTableColumn<Delivery>[] = [
    {
        key: 'member',
        header: 'Member',
        render: (row) => (
            <div>
                <div className="font-medium">{row.member.name ?? '—'}</div>
                <div className="text-muted-foreground text-xs">
                    {row.member.customer_id}
                </div>
            </div>
        ),
    },
    {
        key: 'item_name',
        header: 'Item',
        render: (row) => `${row.item_name} — ${row.weight} g ${row.metal}`,
    },
    {
        key: 'total_invoice_amount',
        header: 'Bill total',
        render: (row) => `₹${row.total_invoice_amount}`,
    },
    {
        key: 'delivered_at',
        header: 'Delivered',
        render: (row) => formatDate(row.delivered_at),
    },
    {
        key: 'invoice_no',
        header: 'Bill',
        render: (row) =>
            row.invoice_no ? (
                <Link
                    href={deliveryInvoice.url(row.id)}
                    className="text-primary inline-flex items-center gap-1 font-medium underline-offset-4 hover:underline"
                >
                    <FileText className="size-4" />
                    {row.invoice_no}
                </Link>
            ) : (
                '—'
            ),
    },
];

/**
 * T-171 (28-09-2026) — the company's own plan-jewellery delivery by Super
 * Admin: a direct entry (no stock), priced like a purchase (locked booking
 * rate for Current Rate members, otherwise today's rate; today's making %
 * and GST), with optional hallmarking, no income, and an immediate bill
 * (DOMAIN_LOGIC.md §16.2 T-171 note).
 */
export default function CompanyDeliveries({
    deliveries,
    lookup,
    gst_percent,
}: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const [customerId, setCustomerId] = useState(lookup?.customer_id ?? '');

    const search: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(
            index.url(),
            { customer_id: customerId || undefined },
            { preserveState: true },
        );
    };

    return (
        <>
            <Head title="Company Plan Deliveries" />

            <div className="flex w-full flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <FormSection
                    icon={Gem}
                    color="amber"
                    title="Deliver Plan Jewellery (Company)"
                    description="For jewellery handed over by the company itself — not from a store's stock. The bill is created at once and no income is paid on it."
                >
                    <form
                        onSubmit={search}
                        className="flex flex-wrap items-end gap-2"
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="lookup_customer_id">
                                Customer ID
                            </Label>
                            <Input
                                id="lookup_customer_id"
                                value={customerId}
                                onChange={(e) => setCustomerId(e.target.value)}
                                placeholder="e.g. GWL123"
                            />
                        </div>
                        <Button type="submit" variant="outline">
                            <Search />
                            Find member
                        </Button>
                    </form>

                    {lookup && !lookup.found && (
                        <p className="text-destructive mt-3 text-sm">
                            No member found with Customer ID{' '}
                            {lookup.customer_id}.
                        </p>
                    )}

                    {lookup && lookup.found && (
                        <DeliveryForm
                            key={lookup.customer_id}
                            lookup={lookup}
                            gstPercent={gst_percent}
                        />
                    )}
                </FormSection>

                <FormSection
                    icon={FileText}
                    color="blue"
                    title="Company Deliveries"
                    description="The last 50 — open a bill to print it or share it on WhatsApp."
                >
                    <DataTable
                        columns={deliveryColumns}
                        rows={deliveries}
                        rowKey={(row) => row.id}
                        emptyMessage="No company delivery yet."
                    />
                </FormSection>
            </div>
        </>
    );
}

function DeliveryForm({
    lookup,
    gstPercent,
}: {
    lookup: Extract<Lookup, { found: true }>;
    gstPercent: number;
}) {
    const form = useForm<{
        customer_id: string;
        item_name: string;
        item_weight: string;
        quantity: string;
        hallmarked: boolean;
        hallmarks: HallmarkPiece[];
    }>({
        customer_id: lookup.customer_id,
        item_name: '',
        item_weight: lookup.entitled_weight_grams ?? '',
        quantity: '1',
        hallmarked: false,
        hallmarks: [],
    });
    const errors = form.errors as Record<string, string | undefined>;

    const weight = Number(form.data.item_weight);
    const quantity = Number(form.data.quantity);
    const rate = Number(lookup.rate_per_gram ?? 0);
    const making = Number(lookup.making_charge_percent ?? 0);
    const hallmarkTotal = form.data.hallmarked
        ? form.data.hallmarks.reduce(
              (sum, piece) => sum + (Number(piece.charge) || 0),
              0,
          )
        : 0;
    const metalValue =
        weight > 0 && quantity >= 1 ? round2(weight * quantity * rate) : 0;
    const makingCharges = round2((metalValue * making) / 100);
    const subtotal = round2(metalValue + makingCharges + hallmarkTotal);
    const gst = round2((subtotal * gstPercent) / 100);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(recordDelivery.url());
    };

    return (
        <div className="mt-4 flex flex-col gap-4">
            <dl className="grid gap-x-6 gap-y-1 rounded-md border p-3 text-sm sm:grid-cols-2">
                <div className="flex justify-between gap-4">
                    <dt className="text-muted-foreground">Member</dt>
                    <dd className="font-medium">
                        {lookup.name ?? '—'} ({lookup.customer_id})
                    </dd>
                </div>
                <div className="flex justify-between gap-4">
                    <dt className="text-muted-foreground">Plan</dt>
                    <dd className="font-medium">
                        {lookup.plan ?? '—'} · {lookup.metal ?? '—'}
                    </dd>
                </div>
                <div className="flex justify-between gap-4">
                    <dt className="text-muted-foreground">Booking</dt>
                    <dd className="font-medium">
                        {BOOKING_LABEL[lookup.booking]}
                    </dd>
                </div>
                <div className="flex justify-between gap-4">
                    <dt className="text-muted-foreground">
                        {lookup.rate_is_locked ? 'Locked rate' : "Today's rate"}
                    </dt>
                    <dd className="font-medium">
                        {formatRatePer10g(lookup.rate_per_gram)}
                    </dd>
                </div>
                {lookup.entitled_weight_grams && (
                    <div className="flex justify-between gap-4">
                        <dt className="text-muted-foreground">
                            Entitled weight
                        </dt>
                        <dd className="font-medium">
                            {lookup.entitled_weight_grams} g
                        </dd>
                    </div>
                )}
                {lookup.commitment_amount && (
                    <div className="flex justify-between gap-4">
                        <dt className="text-muted-foreground">
                            Committed value
                        </dt>
                        <dd className="font-medium">
                            ₹{lookup.commitment_amount}
                        </dd>
                    </div>
                )}
            </dl>

            {lookup.blocked_reason ? (
                <p className="text-destructive text-sm">
                    {lookup.blocked_reason}
                </p>
            ) : (
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="grid gap-3 sm:grid-cols-3">
                        <div className="grid gap-2 sm:col-span-3">
                            <Label htmlFor="cd_item_name">Item</Label>
                            <Input
                                id="cd_item_name"
                                value={form.data.item_name}
                                onChange={(e) =>
                                    form.setData('item_name', e.target.value)
                                }
                                placeholder="e.g. Silver anklet pair"
                                required
                            />
                            {errors.item_name && (
                                <p className="text-destructive text-sm">
                                    {errors.item_name}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="cd_weight">
                                Weight per piece (g)
                            </Label>
                            <Input
                                id="cd_weight"
                                type="number"
                                step="0.001"
                                min="0.001"
                                value={form.data.item_weight}
                                onChange={(e) =>
                                    form.setData('item_weight', e.target.value)
                                }
                                required
                            />
                            {errors.item_weight && (
                                <p className="text-destructive text-sm">
                                    {errors.item_weight}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="cd_quantity">Quantity</Label>
                            <Input
                                id="cd_quantity"
                                type="number"
                                min="1"
                                max="100"
                                value={form.data.quantity}
                                onChange={(e) =>
                                    form.setData('quantity', e.target.value)
                                }
                                required
                            />
                        </div>
                    </div>

                    <HallmarkFields
                        idPrefix="company_delivery"
                        quantity={quantity}
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

                    <dl className="bg-muted/40 flex flex-col gap-1 rounded-md border p-3 text-sm">
                        {[
                            ['Metal value', inr(metalValue)],
                            [`Making charges (${making}%)`, inr(makingCharges)],
                            ['Hallmark charges', inr(hallmarkTotal)],
                            ['Subtotal', inr(subtotal)],
                            [`GST (${gstPercent}%)`, inr(gst)],
                        ].map(([label, value]) => (
                            <div
                                key={label}
                                className="flex justify-between gap-4"
                            >
                                <dt className="text-muted-foreground">
                                    {label}
                                </dt>
                                <dd className="tabular-nums">{value}</dd>
                            </div>
                        ))}
                        <div className="mt-1 flex justify-between gap-4 border-t pt-2 font-semibold">
                            <dt>Bill total</dt>
                            <dd className="tabular-nums">
                                {inr(round2(subtotal + gst))}
                            </dd>
                        </div>
                    </dl>

                    {errors.customer_id && (
                        <p className="text-destructive text-sm">
                            {errors.customer_id}
                        </p>
                    )}

                    <Button
                        type="submit"
                        disabled={form.processing}
                        className="self-start"
                    >
                        Record delivery &amp; generate bill
                    </Button>
                </form>
            )}
        </div>
    );
}

import { Head, router, useForm, usePage } from '@inertiajs/react';
import { CalendarClock, ShoppingBag } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { FormSection } from '@/components/form-section';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/utils';
import {
    deliverPiece,
    deliverSilver,
    store as requestBooking,
} from '@/routes/admin/store-emi';

type Estimate = {
    total_value: number;
    first_installment: number;
    last_installment: number;
} | null;

type Item = {
    id: number;
    item_name: string;
    metal: 'gold' | 'silver';
    weight: number;
    quantity: number;
    estimates: Record<'10' | '20', Estimate>;
};

type Booking = {
    id: number;
    requested_at: string | null;
    member: { customer_id: string | null; name: string | null };
    item_name: string;
    metal: 'gold' | 'silver';
    weight_grams: number;
    installment_count: number;
    status:
        | 'pending'
        | 'cancelled'
        | 'active'
        | 'completed'
        | 'broken'
        | 'delivered';
    paid_installments: number | null;
    cancel_message: string | null;
    /** T-185b — silver owed after a break. */
    silver_grams_owed: string | null;
};

type Props = { items: Item[]; bookings: Booking[] };

const inr = (value: number) =>
    `₹${value.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const STATUS_LABEL: Record<Booking['status'], string> = {
    pending: 'Waiting for Super Admin',
    cancelled: 'Cancelled',
    active: 'EMIs running',
    completed: 'Fully paid',
    broken: 'Broken (3 overdue)',
    delivered: 'Delivered',
};

const columns: DataTableColumn<Booking>[] = [
    {
        key: 'member',
        header: 'Member',
        render: (row) => (
            <div>
                <div className="font-medium">{row.member.name ?? '—'}</div>
                <div className="text-muted-foreground text-xs">
                    {row.member.customer_id} · {formatDate(row.requested_at)}
                </div>
            </div>
        ),
    },
    {
        key: 'item',
        header: 'Piece',
        render: (row) => (
            <div>
                <div>{row.item_name}</div>
                <div className="text-muted-foreground text-xs capitalize">
                    {row.weight_grams} g {row.metal}
                </div>
            </div>
        ),
    },
    {
        key: 'emis',
        header: 'EMIs',
        render: (row) =>
            row.paid_installments !== null
                ? `${row.paid_installments} / ${row.installment_count} paid`
                : `${row.installment_count}`,
    },
    {
        key: 'status',
        header: 'Status',
        render: (row) => (
            <div>
                <Badge
                    variant={
                        row.status === 'cancelled' || row.status === 'broken'
                            ? 'destructive'
                            : row.status === 'pending'
                              ? 'secondary'
                              : 'default'
                    }
                >
                    {STATUS_LABEL[row.status]}
                </Badge>
                {row.cancel_message && (
                    <div className="text-muted-foreground mt-1 max-w-xs text-xs">
                        “{row.cancel_message}”
                    </div>
                )}
            </div>
        ),
    },
];

/**
 * T-185a (DOMAIN_LOGIC.md §16.13) — the Store Admin asks for a member's Repurchase on EMI: one piece of this store's
 * stock over 10 or 20 Current Rate EMIs. Super Admin approves it and locks the rate on that day; the member then pays
 * each EMI from their own EMI page, and the piece is handed over after the last one.
 */
export default function StoreEmi({ items, bookings }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const form = useForm({
        customer_id: '',
        inventory_item_id: items[0]?.id ?? 0,
        installment_count: 10 as 10 | 20,
    });

    const item = items.find(
        (i) => i.id === Number(form.data.inventory_item_id),
    );
    const estimate =
        item?.estimates[String(form.data.installment_count) as '10' | '20'] ??
        null;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(requestBooking.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset('customer_id'),
        });
    };

    return (
        <>
            <Head title="Repurchase on EMI" />

            <div className="flex w-full flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <FormSection
                    icon={ShoppingBag}
                    color="blue"
                    title="Request a Repurchase on EMI"
                    description="For an existing member only. The rate is fixed on the day Super Admin approves; until then the figures below are today's estimate. The piece is held from your stock on approval and handed over after the last EMI."
                    contentClassName="flex flex-col gap-4"
                >
                    {items.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            Your store has no piece in stock.
                        </p>
                    ) : (
                        <form
                            onSubmit={submit}
                            className="grid gap-4 md:grid-cols-2"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="customer_id">
                                    Member&apos;s Customer ID
                                </Label>
                                <Input
                                    id="customer_id"
                                    value={form.data.customer_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'customer_id',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. GWL25"
                                    required
                                />
                                {form.errors.customer_id && (
                                    <p className="text-destructive text-sm">
                                        {form.errors.customer_id}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="inventory_item_id">Piece</Label>
                                <select
                                    id="inventory_item_id"
                                    className="border-input bg-background h-9 rounded-md border px-2 text-sm"
                                    value={form.data.inventory_item_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'inventory_item_id',
                                            Number(e.target.value),
                                        )
                                    }
                                >
                                    {items.map((i) => (
                                        <option key={i.id} value={i.id}>
                                            {i.item_name} — {i.weight} g{' '}
                                            {i.metal} ({i.quantity} in stock)
                                        </option>
                                    ))}
                                </select>
                                {form.errors.inventory_item_id && (
                                    <p className="text-destructive text-sm">
                                        {form.errors.inventory_item_id}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label>Number of EMIs</Label>
                                <div className="flex gap-2">
                                    {([10, 20] as const).map((count) => (
                                        <Button
                                            key={count}
                                            type="button"
                                            variant={
                                                form.data.installment_count ===
                                                count
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            onClick={() =>
                                                form.setData(
                                                    'installment_count',
                                                    count,
                                                )
                                            }
                                        >
                                            {count} EMIs
                                        </Button>
                                    ))}
                                </div>
                                {form.errors.installment_count && (
                                    <p className="text-destructive text-sm">
                                        {form.errors.installment_count}
                                    </p>
                                )}
                            </div>

                            <div className="rounded-md border p-3 text-sm">
                                <div className="text-muted-foreground mb-1 flex items-center gap-1 text-xs">
                                    <CalendarClock className="size-3.5" />
                                    Estimate at today&apos;s rate
                                </div>
                                {estimate ? (
                                    <>
                                        <div>
                                            Value (metal + making):{' '}
                                            <span className="font-semibold">
                                                {inr(estimate.total_value)}
                                            </span>
                                        </div>
                                        <div>
                                            EMI:{' '}
                                            {inr(estimate.first_installment)}{' '}
                                            first →{' '}
                                            {inr(estimate.last_installment)}{' '}
                                            last (1% maintenance, reducing
                                            monthly)
                                        </div>
                                    </>
                                ) : (
                                    <div className="text-destructive">
                                        No rate is set for this metal yet.
                                    </div>
                                )}
                            </div>

                            <div className="md:col-span-2">
                                <Button
                                    type="submit"
                                    disabled={form.processing || !estimate}
                                >
                                    Send request to Super Admin
                                </Button>
                            </div>
                        </form>
                    )}
                </FormSection>

                <FormSection
                    icon={ShoppingBag}
                    color="purple"
                    title="This store's Repurchase on EMI"
                    description="The last 50 requests and where each one stands."
                >
                    <DataTable
                        columns={columns}
                        rows={bookings}
                        rowKey={(row) => row.id}
                        emptyMessage="No request yet."
                        renderActions={(row) =>
                            row.status === 'completed' ? (
                                <HandOverPiece booking={row} />
                            ) : row.status === 'broken' &&
                              Number(row.silver_grams_owed) > 0 ? (
                                <HandOverSilver booking={row} items={items} />
                            ) : null
                        }
                    />
                </FormSection>
            </div>
        </>
    );
}

/**
 * T-185c (DOMAIN_LOGIC.md §16.13 point 4) — hand the fully paid piece over. The EMIs already paid its metal value and
 * making; the member pays GST (and any hallmark added on the bill) now.
 */
function HandOverPiece({ booking }: { booking: Booking }) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">Hand over piece</Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Hand over {booking.item_name}?</DialogTitle>
                    <DialogDescription>
                        Give {booking.member.customer_id} the piece held for
                        them ({booking.weight_grams} g {booking.metal}). Its
                        value and making were paid through the EMIs — collect
                        only the GST now (plus hallmarking if you add it on the
                        bill). The amount to collect is shown after you confirm.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Back
                    </Button>
                    <Button
                        disabled={processing}
                        onClick={() =>
                            router.post(
                                deliverPiece.url(booking.id),
                                {},
                                {
                                    onStart: () => setProcessing(true),
                                    onFinish: () => setProcessing(false),
                                },
                            )
                        }
                    >
                        Hand over
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/**
 * T-185c — hand the silver owed after a break over as a silver piece of at least that weight. The owed grams are
 * prepaid at today's rate; the member pays the extra grams, the making and GST now.
 */
function HandOverSilver({
    booking,
    items,
}: {
    booking: Booking;
    items: Item[];
}) {
    const grams = Number(booking.silver_grams_owed);
    const pieces = items.filter(
        (i) => i.metal === 'silver' && i.weight >= grams,
    );
    const [open, setOpen] = useState(false);
    const [itemId, setItemId] = useState<number | null>(pieces[0]?.id ?? null);
    const [processing, setProcessing] = useState(false);
    const errors = usePage().props.errors as Record<string, string>;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Hand over {booking.silver_grams_owed} g silver
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Hand over silver</DialogTitle>
                    <DialogDescription>
                        {booking.member.customer_id} is owed{' '}
                        {booking.silver_grams_owed} g of silver. Choose a silver
                        piece of at least that weight: the extra grams,
                        today&apos;s making and GST are paid now.
                    </DialogDescription>
                </DialogHeader>
                {pieces.length === 0 ? (
                    <p className="text-destructive text-sm">
                        No silver piece of {booking.silver_grams_owed} g or more
                        is in stock.
                    </p>
                ) : (
                    <select
                        className="border-input bg-background h-9 rounded-md border px-2 text-sm"
                        value={itemId ?? ''}
                        onChange={(e) => setItemId(Number(e.target.value))}
                    >
                        {pieces.map((i) => (
                            <option key={i.id} value={i.id}>
                                {i.item_name} — {i.weight} g ({i.quantity} in
                                stock)
                            </option>
                        ))}
                    </select>
                )}
                {(errors?.inventory_item_id ?? errors?.booking) && (
                    <p className="text-destructive text-sm">
                        {errors.inventory_item_id ?? errors.booking}
                    </p>
                )}
                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Back
                    </Button>
                    <Button
                        disabled={processing || itemId === null}
                        onClick={() =>
                            router.post(
                                deliverSilver.url(booking.id),
                                { inventory_item_id: itemId },
                                {
                                    onStart: () => setProcessing(true),
                                    onFinish: () => setProcessing(false),
                                },
                            )
                        }
                    >
                        Hand over
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

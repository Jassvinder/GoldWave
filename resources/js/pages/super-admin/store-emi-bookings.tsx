import { Head, useForm, usePage } from '@inertiajs/react';
import { ShoppingBag } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { FormSection } from '@/components/form-section';
import { StatStrip } from '@/components/stat-strip';
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
import { Label } from '@/components/ui/label';
import { formatDate, formatRatePer10g } from '@/lib/utils';
import { approve, cancel } from '@/routes/super-admin/store-emi-bookings';

type BookingBase = {
    id: number;
    requested_at: string | null;
    member: { customer_id: string | null; name: string | null };
    store: string;
    requested_by: string;
    item_name: string;
    metal: 'gold' | 'silver';
    weight_grams: number;
    installment_count: number;
};

type Quote = {
    rate_per_gram: number;
    metal_value: number;
    making_charges: number;
    total_value: number;
    first_installment: number;
    last_installment: number;
    total_payable: number;
};

type PendingBooking = BookingBase & {
    store_stock: number;
    quote: Quote | null;
    blocked_reason: string | null;
};

type HistoryRow = BookingBase & {
    status: 'cancelled' | 'active' | 'completed' | 'broken' | 'delivered';
    decided_at: string | null;
    decided_by: string | null;
    cancel_message: string | null;
};

type Props = { pending: PendingBooking[]; history: HistoryRow[] };

const inr = (value: number) =>
    `₹${value.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const STATUS_VARIANT: Record<
    HistoryRow['status'],
    'default' | 'secondary' | 'destructive'
> = {
    active: 'default',
    completed: 'default',
    delivered: 'secondary',
    broken: 'destructive',
    cancelled: 'destructive',
};

const historyColumns: DataTableColumn<HistoryRow>[] = [
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
        key: 'item',
        header: 'Piece',
        render: (row) => (
            <div>
                <div>{row.item_name}</div>
                <div className="text-muted-foreground text-xs capitalize">
                    {row.weight_grams} g {row.metal} · {row.installment_count}{' '}
                    EMIs · {row.store}
                </div>
            </div>
        ),
    },
    {
        key: 'decided_at',
        header: 'Decided',
        render: (row) => (
            <div>
                <div>{formatDate(row.decided_at)}</div>
                {row.decided_by && (
                    <div className="text-muted-foreground text-xs">
                        by {row.decided_by}
                    </div>
                )}
            </div>
        ),
    },
    {
        key: 'status',
        header: 'Status',
        render: (row) => (
            <div>
                <Badge
                    variant={STATUS_VARIANT[row.status]}
                    className="capitalize"
                >
                    {row.status}
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
 * T-185a (DOMAIN_LOGIC.md §16.13) — the queue of Repurchase on EMI requests from the stores. Each card shows what
 * approving now would lock (today's rate, the EMIs) and the piece that will be held; Cancel needs a message the member
 * and the Store Admin will see.
 */
export default function StoreEmiBookings({ pending, history }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;

    return (
        <>
            <Head title="Repurchase EMI Requests" />

            <div className="flex w-full flex-col gap-4 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <StatStrip
                    icon={ShoppingBag}
                    label="Waiting for approval"
                    value={pending.length}
                    counts={[]}
                />

                {pending.length === 0 && (
                    <p className="text-muted-foreground rounded-md border border-dashed p-6 text-center text-sm">
                        No Repurchase on EMI request is waiting.
                    </p>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    {pending.map((booking) => (
                        <PendingCard key={booking.id} booking={booking} />
                    ))}
                </div>

                <FormSection
                    icon={ShoppingBag}
                    color="purple"
                    title="Decided Requests"
                    description="The last 50 approved or cancelled requests, with where each one stands now."
                >
                    <DataTable
                        columns={historyColumns}
                        rows={history}
                        rowKey={(row) => row.id}
                        emptyMessage="Nothing decided yet."
                    />
                </FormSection>
            </div>
        </>
    );
}

function PendingCard({ booking }: { booking: PendingBooking }) {
    const q = booking.quote;
    const approveForm = useForm({});
    const [confirmOpen, setConfirmOpen] = useState(false);
    const approveError = (
        approveForm.errors as Record<string, string | undefined>
    ).booking;

    const submitApprove = () => {
        approveForm.post(approve.url(booking.id), {
            preserveScroll: true,
            onSuccess: () => setConfirmOpen(false),
        });
    };

    const rows: [string, string][] = q
        ? [
              [
                  'Piece',
                  `${booking.item_name} · ${booking.weight_grams} g ${booking.metal}`,
              ],
              ["Today's rate", formatRatePer10g(q.rate_per_gram)],
              ['Total value (metal + making)', inr(q.total_value)],
              [
                  `${booking.installment_count} EMIs`,
                  `${inr(q.first_installment)} first → ${inr(q.last_installment)} last`,
              ],
              ['Total the member pays', inr(q.total_payable)],
              ['Store stock now', String(booking.store_stock)],
          ]
        : [];

    return (
        <div className="bg-card flex flex-col gap-4 rounded-xl border p-4 shadow-sm">
            <div className="flex items-start justify-between gap-2">
                <div>
                    <div className="font-semibold">
                        {booking.member.name ?? 'Member'}{' '}
                        <span className="text-muted-foreground font-normal">
                            ({booking.member.customer_id})
                        </span>
                    </div>
                    <div className="text-muted-foreground text-sm">
                        {booking.store} · by {booking.requested_by} ·{' '}
                        {formatDate(booking.requested_at)}
                    </div>
                </div>
                <Badge variant="secondary">Pending</Badge>
            </div>

            {q ? (
                <dl className="flex flex-col gap-1 text-sm">
                    {rows.map(([label, value]) => (
                        <div key={label} className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">{label}</dt>
                            <dd className="text-right font-medium capitalize">
                                {value}
                            </dd>
                        </div>
                    ))}
                </dl>
            ) : (
                <p className="text-destructive text-sm">
                    Cannot be approved right now: {booking.blocked_reason}
                </p>
            )}

            {approveError && (
                <p className="text-destructive text-sm">{approveError}</p>
            )}

            <div className="flex flex-wrap justify-end gap-2">
                <CancelDialog bookingId={booking.id} />
                <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                    <DialogTrigger asChild>
                        <Button disabled={!q}>Approve</Button>
                    </DialogTrigger>
                    <DialogContent className="sm:max-w-sm">
                        <DialogHeader>
                            <DialogTitle>
                                Approve this Repurchase on EMI?
                            </DialogTitle>
                            <DialogDescription>
                                Today&apos;s rate is locked for{' '}
                                {booking.member.customer_id}, the piece is held
                                at {booking.store} (stock − 1), and EMI #1 is
                                due today.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setConfirmOpen(false)}
                            >
                                Back
                            </Button>
                            <Button
                                onClick={submitApprove}
                                disabled={approveForm.processing}
                            >
                                Approve
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    );
}

function CancelDialog({ bookingId }: { bookingId: number }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ cancel_message: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(cancel.url(bookingId), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">Cancel request</Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Cancel Repurchase on EMI request</DialogTitle>
                    <DialogDescription>
                        The member and the Store Admin see this message. Nothing
                        was held, so the store&apos;s stock does not change.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="flex flex-col gap-3">
                    <div className="grid gap-2">
                        <Label htmlFor={`cancel_message_${bookingId}`}>
                            Message
                        </Label>
                        <textarea
                            id={`cancel_message_${bookingId}`}
                            value={form.data.cancel_message}
                            onChange={(e) =>
                                form.setData('cancel_message', e.target.value)
                            }
                            maxLength={500}
                            rows={3}
                            required
                            className="border-input bg-background focus-visible:ring-ring rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                        />
                        {form.errors.cancel_message && (
                            <p className="text-destructive text-sm">
                                {form.errors.cancel_message}
                            </p>
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Back
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Cancel request
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

import { Head, useForm, usePage } from '@inertiajs/react';
import { Coins } from 'lucide-react';
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
import { approve, cancel } from '@/routes/super-admin/rate-booking-requests';

type Quote = {
    metal: string | null;
    fixed_weight_grams: number;
    metal_rate_id: number;
    rate_per_gram: number;
    metal_value: number;
    making_charges: number;
    total_value: number;
    paid_installments: number;
    pending_installments: number;
    installment_amount: number;
    last_installment_amount: number;
};

type PendingRequest = {
    id: number;
    requested_at: string | null;
    member: { customer_id: string | null; name: string | null };
    plan: string | null;
    quote: Quote | null;
    blocked_reason: string | null;
};

type HistoryRow = {
    id: number;
    status: 'approved' | 'cancelled';
    requested_at: string | null;
    decided_at: string | null;
    decided_by: string | null;
    cancel_message: string | null;
    member: { customer_id: string | null; name: string | null };
};

type Props = { pending: PendingRequest[]; history: HistoryRow[] };

const inr = (value: number) =>
    `₹${value.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

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
        key: 'requested_at',
        header: 'Requested',
        render: (row) => formatDate(row.requested_at),
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
                    variant={
                        row.status === 'approved' ? 'default' : 'destructive'
                    }
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
 * T-166 (28-09-2026) — Super Admin's queue of Current Rate booking requests
 * (DOMAIN_LOGIC.md §3.0). Each card shows what approving now would lock and
 * the metal to buy; Cancel needs a message the member will see.
 */
export default function RateBookingRequests({ pending, history }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;

    return (
        <>
            <Head title="Rate Booking Requests" />

            <div className="flex w-full flex-col gap-4 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <StatStrip
                    icon={Coins}
                    label="Waiting for approval"
                    value={pending.length}
                    counts={[]}
                />

                {pending.length === 0 && (
                    <p className="text-muted-foreground rounded-md border border-dashed p-6 text-center text-sm">
                        No Current Rate booking request is waiting.
                    </p>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    {pending.map((request) => (
                        <PendingCard key={request.id} request={request} />
                    ))}
                </div>

                <FormSection
                    icon={Coins}
                    color="purple"
                    title="Decided Requests"
                    description="The last 50 approved or cancelled requests."
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

function PendingCard({ request }: { request: PendingRequest }) {
    const q = request.quote;
    const approveForm = useForm({
        metal_rate_id: q?.metal_rate_id ?? 0,
        paid_installments: q?.paid_installments ?? 0,
    });
    const [confirmOpen, setConfirmOpen] = useState(false);
    const approveError = (
        approveForm.errors as Record<string, string | undefined>
    ).booking;

    const submitApprove = () => {
        approveForm.post(approve.url(request.id), {
            preserveScroll: true,
            onSuccess: () => setConfirmOpen(false),
        });
    };

    const rows: [string, string][] = q
        ? [
              [
                  'Metal to buy',
                  `${q.fixed_weight_grams} g ${q.metal ?? ''}`.trim(),
              ],
              ["Today's rate", formatRatePer10g(q.rate_per_gram)],
              ['Metal value', inr(q.metal_value)],
              ['Making charges', inr(q.making_charges)],
              ['Total value', inr(q.total_value)],
              [
                  'EMIs',
                  `${q.paid_installments} paid · ${q.pending_installments} pending today`,
              ],
              [
                  'New EMI',
                  `${inr(q.installment_amount)} first → ${inr(q.last_installment_amount)} last`,
              ],
          ]
        : [];

    return (
        <div className="bg-card flex flex-col gap-4 rounded-xl border p-4 shadow-sm">
            <div className="flex items-start justify-between gap-2">
                <div>
                    <div className="font-semibold">
                        {request.member.name ?? 'Member'}{' '}
                        <span className="text-muted-foreground font-normal">
                            ({request.member.customer_id})
                        </span>
                    </div>
                    <div className="text-muted-foreground text-sm">
                        {request.plan ?? '—'} · requested{' '}
                        {formatDate(request.requested_at)}
                    </div>
                </div>
                <Badge variant="secondary">Pending</Badge>
            </div>

            {q ? (
                <dl className="flex flex-col gap-1 text-sm">
                    {rows.map(([label, value]) => (
                        <div key={label} className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">{label}</dt>
                            <dd className="text-right font-medium">{value}</dd>
                        </div>
                    ))}
                </dl>
            ) : (
                <p className="text-destructive text-sm">
                    Cannot be approved right now: {request.blocked_reason}
                </p>
            )}

            {approveError && (
                <p className="text-destructive text-sm">{approveError}</p>
            )}

            <div className="flex flex-wrap justify-end gap-2">
                <CancelDialog requestId={request.id} />
                <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                    <DialogTrigger asChild>
                        <Button disabled={!q}>Approve</Button>
                    </DialogTrigger>
                    <DialogContent className="sm:max-w-sm">
                        <DialogHeader>
                            <DialogTitle>Approve this booking?</DialogTitle>
                            <DialogDescription>
                                Today&apos;s rate is locked for{' '}
                                {request.member.customer_id} and their{' '}
                                {q?.pending_installments} pending EMIs are
                                re-priced. Buy the metal now.
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

function CancelDialog({ requestId }: { requestId: number }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ cancel_message: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(cancel.url(requestId), {
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
                    <DialogTitle>Cancel booking request</DialogTitle>
                    <DialogDescription>
                        The member sees this message on their EMI Schedule page
                        and can request again.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="flex flex-col gap-3">
                    <div className="grid gap-2">
                        <Label htmlFor={`cancel_message_${requestId}`}>
                            Message to the member
                        </Label>
                        <textarea
                            id={`cancel_message_${requestId}`}
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
                            type="submit"
                            variant="destructive"
                            disabled={form.processing}
                        >
                            Cancel request
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

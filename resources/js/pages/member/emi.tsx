import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { formatDate, formatRatePer10g } from '@/lib/utils';
import { pay as payInstallment } from '@/routes/member/emi';

type Installment = {
    id: number;
    installment_no: number;
    due_date: string;
    amount: string;
    status: 'upcoming' | 'due' | 'paid' | 'failed' | 'overdue';
    paid_at: string | null;
    payment_reference: string | null;
    payment_mode: 'online' | 'cash' | null;
};

type PairEligibility = {
    required_emis: number;
    completed_emis: number;
    eligible: boolean;
};

type Props = {
    schedule: {
        rate_booking_method: string;
        installment_amount: string;
        total_installments: number;
        booked_at: string | null;
        rate_per_gram: string | null;
        fixed_weight_grams: string | null;
        pending_installments: number;
    } | null;
    installments: Installment[];
    pair_eligibility: PairEligibility | null;
    /** T-166 — the latest Current Rate booking request, unless it was approved. */
    booking_request: {
        status: 'pending' | 'cancelled';
        requested_at: string | null;
        decided_at: string | null;
        cancel_message: string | null;
    } | null;
};

const STATUS_VARIANT: Record<
    Installment['status'],
    'default' | 'secondary' | 'destructive'
> = {
    upcoming: 'secondary',
    due: 'default',
    paid: 'default',
    failed: 'destructive',
    overdue: 'destructive',
};

/**
 * INSTRUCTIONS.md M06 — full EMI Schedule: installment list, paid
 * date/reference/mode, Pay action, and the Pair/Reward eligibility indicator
 * (DOMAIN_LOGIC.md §5/§7.3). Only the earliest unpaid (due/overdue)
 * installment is payable (§5 item 8) — the Pay action only ever appears next
 * to that one row.
 */
export default function Emi({
    schedule,
    installments,
    pair_eligibility,
    booking_request,
}: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const paymentError = (usePage().props.errors as Record<string, string>)
        ?.payment;
    const [mode, setMode] = useState<'online' | 'cash'>('online');

    const nextPayable = installments.find(
        (installment) =>
            installment.status === 'due' || installment.status === 'overdue',
    );

    function pay(installmentId: number) {
        router.post(payInstallment.url(installmentId), { mode });
    }

    const columns: DataTableColumn<Installment>[] = [
        {
            key: 'installment_no',
            header: 'Installment',
            render: (row) => (
                <span className="font-medium">#{row.installment_no}</span>
            ),
        },
        {
            key: 'due_date',
            header: 'Due Date',
            render: (row) => formatDate(row.due_date),
        },
        { key: 'amount', header: 'Amount', render: (row) => `₹${row.amount}` },
        {
            key: 'paid_at',
            header: 'Paid',
            render: (row) =>
                row.paid_at ? (
                    <span>
                        {formatDate(row.paid_at)} via {row.payment_mode}
                        {row.payment_reference
                            ? ` · Ref ${row.payment_reference}`
                            : ''}
                    </span>
                ) : (
                    '—'
                ),
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => (
                <Badge
                    variant={STATUS_VARIANT[row.status]}
                    className="capitalize"
                >
                    {row.status}
                </Badge>
            ),
        },
    ];

    return (
        <>
            <Head title="EMI Schedule" />

            <div className="flex w-full flex-col gap-6 p-4">
                {paymentError && (
                    <p
                        role="alert"
                        className="border-destructive/40 bg-destructive/10 text-destructive rounded-md border p-3 text-sm"
                    >
                        {paymentError}
                    </p>
                )}

                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                {booking_request?.status === 'pending' && (
                    <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                        Your Current Rate booking request (sent{' '}
                        {formatDate(booking_request.requested_at)}) is waiting
                        for Super Admin approval. The EMIs below stay as they
                        are until it is approved.
                    </div>
                )}

                {booking_request?.status === 'cancelled' && (
                    <div
                        role="alert"
                        className="border-destructive/40 bg-destructive/10 text-destructive rounded-md border p-3 text-sm"
                    >
                        <p className="font-semibold">
                            Your Current Rate booking request was cancelled on{' '}
                            {formatDate(booking_request.decided_at)}.
                        </p>
                        {booking_request.cancel_message && (
                            <p>
                                Message from Super Admin: “
                                {booking_request.cancel_message}”
                            </p>
                        )}
                        <p className="mt-1">
                            You can request again from the Membership Plan page.
                        </p>
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">EMI Schedule</CardTitle>
                        <CardDescription>
                            {!schedule
                                ? 'No EMI schedule on this membership.'
                                : schedule.rate_booking_method ===
                                    'current_rate'
                                  ? schedule.booked_at
                                      ? `Current Rate Booking (booked ${formatDate(schedule.booked_at)}) — ${schedule.fixed_weight_grams}g at ${formatRatePer10g(schedule.rate_per_gram)} · ${schedule.pending_installments} EMIs, each a little less than the one before (see below)`
                                      : `Current Rate Booking — ${schedule.fixed_weight_grams}g at ${formatRatePer10g(schedule.rate_per_gram)} · ${schedule.total_installments} EMIs, reducing every month (see below)`
                                  : `Future Rate — ₹${schedule.installment_amount}/month × ${schedule.total_installments}`}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {pair_eligibility && (
                            <div className="rounded-md border p-3 text-sm">
                                <span className="font-medium">
                                    Pair/Reward eligibility:
                                </span>{' '}
                                {pair_eligibility.eligible ? (
                                    <Badge>Eligible</Badge>
                                ) : (
                                    <Badge variant="secondary">
                                        {pair_eligibility.completed_emis} /{' '}
                                        {pair_eligibility.required_emis}{' '}
                                        installments needed
                                    </Badge>
                                )}
                            </div>
                        )}

                        <DataTable
                            columns={columns}
                            rows={installments}
                            rowKey={(row) => row.id}
                            emptyMessage="No installments to show."
                            renderActions={(row) =>
                                nextPayable?.id === row.id ? (
                                    <div className="flex items-center justify-end gap-2">
                                        <select
                                            className="border-input bg-background rounded-md border px-2 py-1 text-sm"
                                            value={mode}
                                            onChange={(event) =>
                                                setMode(
                                                    event.target.value as
                                                        | 'online'
                                                        | 'cash',
                                                )
                                            }
                                        >
                                            <option value="online">
                                                Online
                                            </option>
                                            <option value="cash">Cash</option>
                                        </select>
                                        <Button
                                            size="sm"
                                            onClick={() => pay(row.id)}
                                        >
                                            Pay
                                        </Button>
                                    </div>
                                ) : null
                            }
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

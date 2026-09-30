import { Head, usePage } from '@inertiajs/react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { EmiPayDialog } from '@/components/emi-pay-dialog';
import { formatDate, formatRatePer10g } from '@/lib/utils';
import {
    payAll,
    pay as payInstallment,
    storePayAll,
} from '@/routes/member/emi';

type Installment = {
    id: number;
    installment_no: number;
    due_date: string;
    amount: string;
    status: 'upcoming' | 'due' | 'paid' | 'failed' | 'overdue' | 'cancelled';
    paid_at: string | null;
    payment_reference: string | null;
    payment_mode: 'online' | 'cash' | 'upi' | 'wallet' | null;
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
    /** T-182 — earnings held while an EMI is overdue. */
    held_earnings: string;
    /** T-184 — "Pay All Remaining EMIs"; null when nothing is left to pay. */
    full_payment: FullPaymentOffer | null;
    /** T-185a — the member's Repurchase on EMI (a store piece over 10 or 20 Current Rate EMIs). */
    store_emi: StoreEmi | null;
};

type FullPaymentOffer = {
    count: number;
    amount: number | null;
    regular_total: number | null;
    saving: number | null;
    blocked_reason: string | null;
};

type StoreEmi = {
    id: number;
    status: 'pending' | 'cancelled' | 'active' | 'completed' | 'broken';
    item_name: string;
    metal: 'gold' | 'silver';
    weight_grams: number;
    installment_count: number;
    store: string;
    requested_at: string | null;
    decided_at: string | null;
    cancel_message: string | null;
    broken_at: string | null;
    principal_paid: string | null;
    silver_grams_owed: string | null;
    silver_rate_per_gram: string | null;
    rate_per_gram: string | null;
    installments: Installment[];
    full_payment: FullPaymentOffer | null;
};

const money = (value: number | string) =>
    `₹${Number(value).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const STATUS_VARIANT: Record<
    Installment['status'],
    'default' | 'secondary' | 'destructive'
> = {
    upcoming: 'secondary',
    due: 'default',
    paid: 'default',
    failed: 'destructive',
    overdue: 'destructive',
    cancelled: 'secondary',
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
    held_earnings,
    full_payment,
    store_emi,
}: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const errors = usePage().props.errors as Record<string, string>;
    const paymentError =
        errors?.payment ?? errors?.installment ?? errors?.full_payment;
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

                {[...installments, ...(store_emi?.installments ?? [])].some(
                    (row) => row.status === 'overdue',
                ) && (
                    <div
                        role="alert"
                        className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200"
                    >
                        <p className="font-semibold">
                            An EMI is overdue — your earnings are on hold.
                        </p>
                        <p>
                            {Number(held_earnings) > 0
                                ? `₹${held_earnings} is held so far. `
                                : ''}
                            Pay your overdue EMI and everything held is added to
                            your wallet at once. Pair/Reward and Booster payouts
                            also wait until then.
                        </p>
                    </div>
                )}

                {full_payment && (
                    <PayAllCard
                        offer={full_payment}
                        url={payAll.url()}
                        isCurrentRate={
                            schedule?.rate_booking_method === 'current_rate'
                        }
                    />
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

                        <InstallmentTable installments={installments} />
                    </CardContent>
                </Card>

                {store_emi && <StoreEmiSection storeEmi={store_emi} />}
            </div>
        </>
    );
}

/**
 * T-184 (DOMAIN_LOGIC.md §5 point 9) — pay every remaining EMI in one payment. On Current Rate the amount leaves out
 * maintenance. A confirm dialog repeats the amount before anything is submitted.
 */
function PayAllCard({
    offer,
    isCurrentRate,
    url,
    title = 'Pay All Remaining EMIs',
}: {
    offer: FullPaymentOffer;
    isCurrentRate: boolean;
    url: string;
    title?: string;
}) {
    const emis = `${offer.count} EMI${offer.count === 1 ? '' : 's'}`;
    const showSaving = isCurrentRate && Number(offer.saving) > 0;

    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>
                    Clear your {emis} in one payment and your plan jewellery
                    becomes ready for delivery.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {offer.blocked_reason || offer.amount === null ? (
                    <Alert>
                        <AlertTitle>Not available right now</AlertTitle>
                        <AlertDescription>
                            {offer.blocked_reason}
                        </AlertDescription>
                    </Alert>
                ) : (
                    <div className="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <div className="text-muted-foreground text-sm">
                                Amount to pay now
                            </div>
                            <div className="text-2xl font-semibold">
                                {money(offer.amount)}
                            </div>
                            {showSaving && (
                                <div className="text-sm text-green-700 dark:text-green-400">
                                    Without maintenance — you save{' '}
                                    {money(offer.saving ?? 0)} (EMIs as
                                    scheduled: {money(offer.regular_total ?? 0)}
                                    )
                                </div>
                            )}
                        </div>

                        <EmiPayDialog
                            url={url}
                            amount={offer.amount}
                            title="Pay all remaining EMIs?"
                            description={`This one payment settles all ${emis}. Cash and GPay/UPI payments are confirmed by the company first.`}
                            trigger={<Button>Pay All Remaining EMIs</Button>}
                        >
                            <dl className="flex flex-col gap-2 text-sm">
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        EMIs settled
                                    </dt>
                                    <dd className="font-medium">
                                        {offer.count}
                                    </dd>
                                </div>
                                {showSaving && (
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-muted-foreground">
                                            Maintenance left out
                                        </dt>
                                        <dd className="font-medium">
                                            {money(offer.saving ?? 0)}
                                        </dd>
                                    </div>
                                )}
                                <div className="flex justify-between gap-4 border-t pt-2">
                                    <dt className="font-medium">Amount</dt>
                                    <dd className="font-semibold">
                                        {money(offer.amount)}
                                    </dd>
                                </div>
                            </dl>
                        </EmiPayDialog>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

/**
 * The installment list with the Pay action on the single next payable (due/overdue) EMI (DOMAIN_LOGIC.md §5 item 8) —
 * shared by the plan schedule and a store Repurchase on EMI (T-185a).
 */
function InstallmentTable({ installments }: { installments: Installment[] }) {
    const nextPayable = installments.find(
        (installment) =>
            installment.status === 'due' || installment.status === 'overdue',
    );

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
        <DataTable
            columns={columns}
            rows={installments}
            rowKey={(row) => row.id}
            emptyMessage="No installments to show."
            renderActions={(row) =>
                nextPayable?.id === row.id ? (
                    <div className="flex items-center justify-end">
                        <EmiPayDialog
                            url={payInstallment.url(row.id)}
                            amount={row.amount}
                            title={`Pay EMI #${row.installment_no}`}
                            description={`Due ${formatDate(row.due_date)}. Cash and GPay/UPI payments are confirmed by the company first.`}
                            trigger={<Button size="sm">Pay</Button>}
                        />
                    </div>
                ) : null
            }
        />
    );
}

/**
 * T-185a (DOMAIN_LOGIC.md §16.13) — the member's Repurchase on EMI: waiting for approval, cancelled (with the message),
 * running (its EMIs, paid exactly like plan EMIs, and "Pay All Remaining EMIs" without maintenance), or fully paid.
 */
function StoreEmiSection({ storeEmi }: { storeEmi: StoreEmi }) {
    const piece = `${storeEmi.item_name} — ${storeEmi.weight_grams} g ${storeEmi.metal}`;

    if (storeEmi.status === 'pending') {
        return (
            <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                Your Repurchase on EMI of <strong>{piece}</strong> at{' '}
                {storeEmi.store} ({storeEmi.installment_count} EMIs, requested{' '}
                {formatDate(storeEmi.requested_at)}) is waiting for Super Admin
                approval. The rate is fixed on the day it is approved.
            </div>
        );
    }

    if (storeEmi.status === 'cancelled') {
        return (
            <div
                role="alert"
                className="border-destructive/40 bg-destructive/10 text-destructive rounded-md border p-3 text-sm"
            >
                <p className="font-semibold">
                    Your Repurchase on EMI request ({piece}) was cancelled on{' '}
                    {formatDate(storeEmi.decided_at)}.
                </p>
                {storeEmi.cancel_message && (
                    <p>Message: “{storeEmi.cancel_message}”</p>
                )}
            </div>
        );
    }

    return (
        <>
            {storeEmi.status === 'active' && storeEmi.full_payment && (
                <PayAllCard
                    offer={storeEmi.full_payment}
                    isCurrentRate
                    url={storePayAll.url(storeEmi.id)}
                    title="Pay All Remaining Repurchase EMIs"
                />
            )}

            <Card>
                <CardHeader>
                    <CardTitle className="text-2xl">
                        Repurchase on EMI
                    </CardTitle>
                    <CardDescription>
                        {piece} at {storeEmi.store} · rate locked at{' '}
                        {formatRatePer10g(storeEmi.rate_per_gram)} on{' '}
                        {formatDate(storeEmi.decided_at)} ·{' '}
                        {storeEmi.installment_count} EMIs, each a little less
                        than the one before
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-3">
                    {storeEmi.status === 'broken' && (
                        <div
                            role="alert"
                            className="border-destructive/40 bg-destructive/10 text-destructive rounded-md border p-3 text-sm"
                        >
                            <p className="font-semibold">
                                Closed on {formatDate(storeEmi.broken_at)} after
                                3 overdue EMIs.
                            </p>
                            {Number(storeEmi.silver_grams_owed) > 0 ? (
                                <p>
                                    For the ₹{storeEmi.principal_paid} you paid
                                    (maintenance excluded) you get{' '}
                                    <strong>
                                        {storeEmi.silver_grams_owed} g of silver
                                    </strong>{' '}
                                    at{' '}
                                    {formatRatePer10g(
                                        storeEmi.silver_rate_per_gram,
                                    )}
                                    . Collect it at {storeEmi.store}; that
                                    day&apos;s making and GST are paid at
                                    collection.
                                </p>
                            ) : (
                                <p>No EMI was paid, so nothing is owed.</p>
                            )}
                        </div>
                    )}
                    {storeEmi.status === 'completed' && (
                        <div className="rounded-md border border-green-300 bg-green-50 p-3 text-sm text-green-900 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
                            Every EMI is paid — your piece is ready to collect
                            at {storeEmi.store}.
                        </div>
                    )}
                    <InstallmentTable installments={storeEmi.installments} />
                </CardContent>
            </Card>
        </>
    );
}

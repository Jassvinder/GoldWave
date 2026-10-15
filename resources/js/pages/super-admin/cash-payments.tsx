import { Head, router, usePage, usePoll } from '@inertiajs/react';
import { Banknote, Eye } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';
import { DataPagination } from '@/components/data-pagination';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { FilterBar } from '@/components/filter-bar';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDate } from '@/lib/utils';
import * as cashPayments from '@/routes/super-admin/cash-payments';
import { show as showMember } from '@/routes/super-admin/members';
import type { Paginated } from '@/types';

type PendingPayment = {
    id: number;
    type: 'registration' | 'emi_installment';
    mode: 'cash' | 'upi';
    amount: string;
    created_at: string;
    member: {
        customer_id: string | null;
        user: { name: string; email: string } | null;
    } | null;
    emi_installment: { installment_no: number } | null;
    /** T-184 — set on a "Pay All Remaining EMIs" payment. */
    covers_installments: number | null;
    /** T-196 — GPay/UPI proof. */
    upi_reference: string | null;
    upi_screenshot_url: string | null;
    /** T-228 — its approval is running in the background. */
    approving: boolean;
};

/** T-226 — a Cash / GPay-UPI payment that was approved or rejected. */
type DecidedPayment = {
    id: number;
    type: 'registration' | 'emi_installment';
    covers_installments: number | null;
    installment_no: number | null;
    mode: 'cash' | 'upi';
    amount: string;
    cash_status: 'approved' | 'rejected';
    customer_id: string;
    name: string | null;
    member_id: number;
    upi_reference: string | null;
    upi_screenshot_url: string | null;
    submitted_on: string | null;
    decided_on: string | null;
    decided_time: string | null;
    decided_by: string | null;
    store_name: string | null;
};

type Filters = {
    search?: string;
    status?: string;
    mode?: string;
    from?: string;
    to?: string;
};

type Props = {
    tab: 'pending' | 'history';
    pending: PendingPayment[];
    history: Paginated<DecidedPayment> | null;
    history_total: number;
    filters: Filters;
};

const ANY = '__any__';

function paymentLabel(
    type: 'registration' | 'emi_installment',
    coversInstallments: number | null,
    installmentNo: number | null | undefined,
): string {
    if (type === 'registration') {
        return 'Registration';
    }

    return coversInstallments !== null
        ? `Full payment — ${coversInstallments} EMIs`
        : `EMI Installment #${installmentNo ?? '?'}`;
}

/** Cash, or GPay/UPI with its Ref ID and screenshot link (T-196). */
function PaidBy({
    mode,
    reference,
    screenshotUrl,
}: {
    mode: 'cash' | 'upi';
    reference: string | null;
    screenshotUrl: string | null;
}) {
    if (mode !== 'upi') {
        return <Badge variant="outline">Cash</Badge>;
    }

    return (
        <div className="flex flex-col gap-1">
            <Badge variant="outline">GPay / UPI</Badge>
            <span className="text-xs">
                Ref ID:{' '}
                <span className="font-mono font-medium">
                    {reference ?? '—'}
                </span>
            </span>
            {screenshotUrl ? (
                <a
                    href={screenshotUrl}
                    target="_blank"
                    rel="noreferrer"
                    className="text-primary text-xs underline"
                >
                    View screenshot
                </a>
            ) : (
                <span className="text-muted-foreground text-xs">
                    No screenshot
                </span>
            )}
        </div>
    );
}

/**
 * 15-10-2026 (user feedback) — a payment is approved or rejected only from here, after seeing the proof: the amount,
 * and for GPay/UPI the Ref ID and the screenshot itself (not just a link); for Cash, a reminder to have the money first.
 */
function ReviewPaymentDialog({ row }: { row: PendingPayment }) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const amount = `₹${Number(row.amount).toLocaleString('en-IN')}`;

    const decide = (action: 'approve' | 'reject') => {
        router.post(
            action === 'approve'
                ? cashPayments.approve.url(row.id)
                : cashPayments.reject.url(row.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => setOpen(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Eye />
                    Review
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Review payment</DialogTitle>
                    <DialogDescription>
                        {row.member?.user?.name ?? 'Unknown member'} ·{' '}
                        {row.member?.customer_id ?? '—'}
                    </DialogDescription>
                </DialogHeader>

                <dl className="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt className="text-muted-foreground text-xs">
                            Payment
                        </dt>
                        <dd className="font-medium">
                            {paymentLabel(
                                row.type,
                                row.covers_installments,
                                row.emi_installment?.installment_no,
                            )}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-xs">
                            Amount
                        </dt>
                        <dd className="text-lg font-semibold">{amount}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-xs">
                            Paid by
                        </dt>
                        <dd className="font-medium">
                            {row.mode === 'upi' ? 'GPay / UPI' : 'Cash'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-xs">
                            Submitted
                        </dt>
                        <dd className="font-medium">
                            {formatDate(row.created_at)}
                        </dd>
                    </div>
                </dl>

                {row.mode === 'upi' ? (
                    <div className="flex flex-col gap-3">
                        <div className="rounded-md border px-3 py-2">
                            <div className="text-muted-foreground text-xs">
                                Transaction / UTR Ref ID
                            </div>
                            <div className="font-mono text-base font-semibold">
                                {row.upi_reference ?? '—'}
                            </div>
                        </div>
                        {row.upi_screenshot_url ? (
                            <a
                                href={row.upi_screenshot_url}
                                target="_blank"
                                rel="noreferrer"
                                className="block"
                            >
                                <img
                                    src={row.upi_screenshot_url}
                                    alt="Payment screenshot"
                                    className="bg-muted max-h-96 w-full rounded-md border object-contain"
                                />
                                <span className="text-primary mt-1 block text-xs underline">
                                    Open full size
                                </span>
                            </a>
                        ) : (
                            <p className="text-destructive text-sm">
                                No screenshot was uploaded.
                            </p>
                        )}
                        <p className="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                            Approve only after this Ref ID and {amount} appear
                            in the company bank statement.
                        </p>
                    </div>
                ) : (
                    <p className="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                        Cash has no Ref ID or screenshot. Approve only after you
                        have received {amount} in cash.
                    </p>
                )}

                <DialogFooter className="gap-2">
                    <Button
                        variant="outline"
                        disabled={processing}
                        onClick={() => decide('reject')}
                    >
                        Reject
                    </Button>
                    <Button
                        disabled={processing}
                        onClick={() => decide('approve')}
                    >
                        Approve {amount}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

const pendingColumns: DataTableColumn<PendingPayment>[] = [
    {
        key: 'submitted',
        header: 'Submitted',
        render: (row) => (
            <span className="whitespace-nowrap">
                {formatDate(row.created_at)}
            </span>
        ),
    },
    {
        key: 'member',
        header: 'Member',
        render: (row) => (
            <div>
                <div className="font-medium">
                    {row.member?.user?.name ?? 'Unknown member'}
                </div>
                <div className="text-muted-foreground text-xs">
                    {row.member?.customer_id ?? row.member?.user?.email}
                </div>
            </div>
        ),
    },
    {
        key: 'type',
        header: 'Payment Type',
        render: (row) => (
            <Badge variant="secondary">
                {paymentLabel(
                    row.type,
                    row.covers_installments,
                    row.emi_installment?.installment_no,
                )}
            </Badge>
        ),
    },
    { key: 'amount', header: 'Amount', render: (row) => `₹${row.amount}` },
    {
        key: 'mode',
        header: 'Paid by',
        render: (row) => (
            <div className="flex items-start gap-3">
                <PaidBy
                    mode={row.mode}
                    reference={row.upi_reference}
                    screenshotUrl={row.upi_screenshot_url}
                />
                {row.upi_screenshot_url && (
                    <a
                        href={row.upi_screenshot_url}
                        target="_blank"
                        rel="noreferrer"
                        className="shrink-0"
                    >
                        <img
                            src={row.upi_screenshot_url}
                            alt="Payment screenshot"
                            loading="lazy"
                            className="bg-muted size-14 rounded border object-cover"
                        />
                    </a>
                )}
            </div>
        ),
    },
];

const historyColumns: DataTableColumn<DecidedPayment>[] = [
    {
        key: 'decided_on',
        header: 'Decided',
        render: (row) => (
            <div className="whitespace-nowrap">
                <div className="font-medium">{formatDate(row.decided_on)}</div>
                <div className="text-muted-foreground text-xs">
                    {row.decided_time}
                </div>
            </div>
        ),
    },
    {
        key: 'member',
        header: 'Member',
        render: (row) => (
            <div>
                <div className="font-medium">
                    {row.name ?? 'Unknown member'}
                </div>
                <div className="text-muted-foreground text-xs">
                    {row.customer_id}
                </div>
            </div>
        ),
    },
    {
        key: 'type',
        header: 'Payment Type',
        render: (row) => (
            <div className="flex flex-col items-start gap-1">
                <Badge variant="secondary">
                    {paymentLabel(
                        row.type,
                        row.covers_installments,
                        row.installment_no,
                    )}
                </Badge>
                <span className="text-muted-foreground text-xs">
                    Submitted {formatDate(row.submitted_on)}
                </span>
            </div>
        ),
    },
    { key: 'amount', header: 'Amount', render: (row) => `₹${row.amount}` },
    {
        key: 'mode',
        header: 'Paid by',
        render: (row) => (
            <div className="flex flex-col gap-1">
                <PaidBy
                    mode={row.mode}
                    reference={row.upi_reference}
                    screenshotUrl={row.upi_screenshot_url}
                />
                {row.store_name && (
                    <span className="text-muted-foreground text-xs">
                        Collected by {row.store_name}
                    </span>
                )}
            </div>
        ),
    },
    {
        key: 'status',
        header: 'Status',
        render: (row) => (
            <div className="flex flex-col items-start gap-1">
                <Badge
                    variant={
                        row.cash_status === 'approved'
                            ? 'success'
                            : 'destructive'
                    }
                >
                    {row.cash_status === 'approved' ? 'Approved' : 'Rejected'}
                </Badge>
                <span className="text-muted-foreground text-xs">
                    by {row.decided_by ?? '—'}
                </span>
            </div>
        ),
    },
];

/**
 * T-226 (15-10-2026) — every approved or rejected Cash / GPay-UPI payment, newest decision first: who decided it and
 * when, with search (Customer ID, name, Ref ID) and Status / Mode / date filters. Rows open the member's page.
 */
function HistoryTab({
    history,
    filters,
}: {
    history: Paginated<DecidedPayment>;
    filters: Filters;
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? ANY);
    const [mode, setMode] = useState(filters.mode ?? ANY);
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

    const hasFilters = Boolean(
        filters.search ||
        filters.status ||
        filters.mode ||
        filters.from ||
        filters.to,
    );

    const apply = (overrides: Record<string, string | number> = {}) => {
        router.get(
            cashPayments.index.url(),
            {
                tab: 'history',
                search: search || undefined,
                status: status === ANY ? undefined : status,
                mode: mode === ANY ? undefined : mode,
                from: from || undefined,
                to: to || undefined,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        apply();
    };

    const reset = () => {
        setSearch('');
        setStatus(ANY);
        setMode(ANY);
        setFrom('');
        setTo('');
        router.get(
            cashPayments.index.url(),
            { tab: 'history' },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <div className="flex flex-col gap-4">
            <FilterBar
                search={search}
                onSearchChange={setSearch}
                searchPlaceholder="Search by Customer ID, name or UPI Ref ID"
                onSubmit={submit}
                onReset={reset}
            >
                <Select value={status} onValueChange={setStatus}>
                    <SelectTrigger className="w-full sm:w-36">
                        <SelectValue placeholder="All Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ANY}>All Status</SelectItem>
                        <SelectItem value="approved">Approved</SelectItem>
                        <SelectItem value="rejected">Rejected</SelectItem>
                    </SelectContent>
                </Select>

                <Select value={mode} onValueChange={setMode}>
                    <SelectTrigger className="w-full sm:w-36">
                        <SelectValue placeholder="All Modes" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ANY}>All Modes</SelectItem>
                        <SelectItem value="cash">Cash</SelectItem>
                        <SelectItem value="upi">GPay / UPI</SelectItem>
                    </SelectContent>
                </Select>

                <div className="flex items-center gap-2">
                    <Label htmlFor="history-from" className="sr-only">
                        Decided from
                    </Label>
                    <Input
                        id="history-from"
                        type="date"
                        value={from}
                        max={to || undefined}
                        onChange={(e) => setFrom(e.target.value)}
                        className="w-full sm:w-40"
                        aria-label="Decided from"
                    />
                    <span className="text-muted-foreground text-sm">to</span>
                    <Label htmlFor="history-to" className="sr-only">
                        Decided to
                    </Label>
                    <Input
                        id="history-to"
                        type="date"
                        value={to}
                        min={from || undefined}
                        onChange={(e) => setTo(e.target.value)}
                        className="w-full sm:w-40"
                        aria-label="Decided to"
                    />
                </div>
            </FilterBar>

            <DataTable
                columns={historyColumns}
                rows={history.data}
                rowKey={(row) => row.id}
                rowHref={(row) => showMember.url(row.member_id)}
                emptyMessage={
                    hasFilters
                        ? 'No payments match these filters.'
                        : 'No approved or rejected payments yet.'
                }
            />

            <DataPagination
                paginated={history}
                onPageChange={(page) => apply({ page })}
            />
        </div>
    );
}

/**
 * DOMAIN_LOGIC.md §3.1 step 8 / §10.2 — Super Admin or Admin confirms a Cash or GPay/UPI payment (T-196) before a
 * registration activates or an EMI counts as paid. For GPay/UPI, check the Ref ID and screenshot against the bank
 * statement first. T-226 — the History tab keeps every decision.
 */
export default function CashPayments({
    tab,
    pending,
    history,
    history_total,
    filters,
}: Props) {
    function switchTab(value: string) {
        router.get(
            cashPayments.index.url(),
            value === 'history' ? { tab: 'history' } : {},
            { preserveScroll: true },
        );
    }

    const upiCount = pending.filter((row) => row.mode === 'upi').length;
    const flash = usePage().props.flash as { status?: string } | undefined;

    // T-228 — while an approval runs in the background, refresh the list every few seconds until it is done.
    const anyApproving = pending.some((row) => row.approving);
    const { start, stop } = usePoll(
        3000,
        { only: ['pending', 'history_total'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (anyApproving) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [anyApproving, start, stop]);

    return (
        <>
            <Head title="Payment Approvals" />

            <div className="flex w-full flex-col gap-4 p-4">
                {flash?.status && (
                    <p className="rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200">
                        {flash.status}
                    </p>
                )}

                <StatStrip
                    icon={Banknote}
                    label="Payments awaiting approval"
                    value={pending.length}
                    counts={[
                        {
                            label: 'Cash',
                            value: pending.length - upiCount,
                            color: 'amber',
                        },
                        { label: 'GPay / UPI', value: upiCount, color: 'blue' },
                    ]}
                />

                <Tabs value={tab} onValueChange={switchTab}>
                    <TabsList>
                        <TabsTrigger value="pending">
                            Pending ({pending.length})
                        </TabsTrigger>
                        <TabsTrigger value="history">
                            History ({history_total})
                        </TabsTrigger>
                    </TabsList>
                </Tabs>

                {tab === 'history' && history ? (
                    <HistoryTab history={history} filters={filters} />
                ) : (
                    <>
                        <p className="text-muted-foreground text-sm">
                            For GPay / UPI, match the Ref ID and screenshot with
                            the company bank statement before approving.
                        </p>

                        <DataTable
                            columns={pendingColumns}
                            rows={pending}
                            rowKey={(row) => row.id}
                            emptyMessage="No payments awaiting approval."
                            renderActions={(row) => (
                                <div className="flex justify-end">
                                    {row.approving ? (
                                        <Badge
                                            variant="secondary"
                                            className="gap-1.5"
                                        >
                                            <Spinner className="size-3" />
                                            Processing…
                                        </Badge>
                                    ) : (
                                        <ReviewPaymentDialog row={row} />
                                    )}
                                </div>
                            )}
                        />
                    </>
                )}
            </div>
        </>
    );
}

import { Head, Link, router } from '@inertiajs/react';
import { Network, SearchX, UserSearch } from 'lucide-react';
import { FormEventHandler, useState } from 'react';
import { DataPagination } from '@/components/data-pagination';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { FilterBar } from '@/components/filter-bar';
import { StatStrip } from '@/components/stat-strip';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate } from '@/lib/utils';
import { show as showMember } from '@/routes/super-admin/members';
import { index as uplineEarnings } from '@/routes/super-admin/upline-earnings';
import type { Paginated } from '@/types';

type RowType =
    | 'level_income'
    | 'purchase_repurchase'
    | 'store_profit'
    | 'draw_benefit'
    | 'pair_entry';

type RowStatus = 'paid' | 'held' | 'skipped' | 'pair_used' | 'pair_unused';

type TraceRow = {
    key: string;
    date: string | null;
    type: RowType;
    beneficiary_id: number | null;
    beneficiary_customer_id: string | null;
    beneficiary_name: string | null;
    relation: string;
    source: string;
    base_amount: string | null;
    rate_percent: string | null;
    amount: string | null;
    status: RowStatus;
    reason: string | null;
};

type SummaryRow = {
    beneficiary_id: number;
    customer_id: string;
    name: string | null;
    sponsor_level: number | null;
    paid: string;
    held: string;
    skipped_count: number;
    prizes: number;
    pair_entries: number;
    pair_used: number;
};

type Match = {
    id: number;
    customer_id: string;
    name: string | null;
    mobile: string | null;
};

type TracedMember = {
    id: number;
    customer_id: string;
    name: string | null;
    plan: string | null;
    status: string;
    sponsor_customer_id: string | null;
};

type Filters = {
    type: RowType | null;
    status: RowStatus | null;
    from: string | null;
    to: string | null;
};

type Props = {
    search: string;
    matches: Match[];
    filters: Filters;
    member: TracedMember | null;
    summary: { receivers: SummaryRow[]; pair_only_count: number };
    totals: {
        paid: string;
        held: string;
        skipped: number;
        prizes: number;
        pair_entries: number;
    } | null;
    rows: Paginated<TraceRow> | null;
};

const TYPE_LABELS: Record<RowType, string> = {
    level_income: 'Level Income',
    purchase_repurchase: 'Purchase / Repurchase',
    store_profit: 'Store Profit Share',
    draw_benefit: 'Draw Prize (Sponsor)',
    pair_entry: 'Pair Entry',
};

const STATUS_LABELS: Record<RowStatus, string> = {
    paid: 'Paid',
    held: 'Held',
    skipped: 'Skipped',
    pair_used: 'Pair made',
    pair_unused: 'Pair pending',
};

const STATUS_VARIANT: Record<
    RowStatus,
    'success' | 'default' | 'secondary' | 'outline'
> = {
    paid: 'success',
    held: 'default',
    skipped: 'secondary',
    pair_used: 'success',
    pair_unused: 'outline',
};

const ANY = '__any__';

/** Indian-grouped rupees with paise, e.g. "₹1,00,000.00". */
function inr(value: string | number | null): string {
    if (value === null) {
        return '—';
    }

    return `₹${Number(value).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function levelLabel(level: number | null): string {
    if (level === null) {
        return 'Outside chain';
    }

    return level === 0 ? 'Self' : `Level ${level}`;
}

/**
 * T-238 — Super Admin "Upline Earnings" (INSTRUCTIONS.md S18, DOMAIN_LOGIC.md §12.3): find a member, then see who
 * earned what because of them — a per-member summary on top, every row (paid, held, skipped, prizes, pair entries)
 * below with type/status/date filters. Read-only.
 */
export default function SuperAdminUplineEarnings({
    search,
    matches,
    filters,
    member,
    summary,
    totals,
    rows,
}: Props) {
    const [query, setQuery] = useState(search);
    const [type, setType] = useState<string>(filters.type ?? ANY);
    const [status, setStatus] = useState<string>(filters.status ?? ANY);
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

    const findMember: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(
            uplineEarnings.url(),
            query.trim() ? { search: query.trim() } : {},
        );
    };

    const clearMember = () => {
        setQuery('');
        router.get(uplineEarnings.url());
    };

    const applyFilters = (overrides: Record<string, string | number> = {}) => {
        if (!member) {
            return;
        }

        router.get(
            uplineEarnings.url(),
            {
                member: member.id,
                type: type === ANY ? undefined : type,
                status: status === ANY ? undefined : status,
                from: from || undefined,
                to: to || undefined,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const resetFilters = () => {
        setType(ANY);
        setStatus(ANY);
        setFrom('');
        setTo('');

        if (member) {
            router.get(
                uplineEarnings.url(),
                { member: member.id },
                { preserveState: true, preserveScroll: true },
            );
        }
    };

    const beneficiaryCell = (
        id: number | null,
        customerId: string | null,
        name: string | null,
    ) =>
        id === null ? (
            <span className="text-muted-foreground">No one</span>
        ) : (
            <div className="flex flex-col">
                <Link
                    href={showMember.url(id)}
                    className="text-primary font-medium hover:underline"
                >
                    {customerId}
                </Link>
                {name && (
                    <span className="text-muted-foreground text-xs">
                        {name}
                    </span>
                )}
            </div>
        );

    const summaryColumns: DataTableColumn<SummaryRow>[] = [
        {
            key: 'sponsor_level',
            header: 'Level',
            render: (row) => levelLabel(row.sponsor_level),
        },
        {
            key: 'customer_id',
            header: 'Member',
            render: (row) =>
                beneficiaryCell(row.beneficiary_id, row.customer_id, row.name),
        },
        { key: 'paid', header: 'Paid', render: (row) => inr(row.paid) },
        {
            key: 'held',
            header: 'Held',
            render: (row) => (Number(row.held) > 0 ? inr(row.held) : '—'),
        },
        {
            key: 'skipped_count',
            header: 'Skipped',
            render: (row) => row.skipped_count || '—',
        },
        {
            key: 'prizes',
            header: 'Draw Prizes',
            render: (row) => row.prizes || '—',
        },
        {
            key: 'pair_entries',
            header: 'Pair Entries',
            render: (row) =>
                row.pair_entries
                    ? `${row.pair_entries} (${row.pair_used} made a pair)`
                    : '—',
        },
    ];

    const rowColumns: DataTableColumn<TraceRow>[] = [
        {
            key: 'date',
            header: 'Date',
            render: (row) => formatDate(row.date),
        },
        {
            key: 'type',
            header: 'Type',
            render: (row) => (
                <div className="flex flex-col">
                    <span className="font-medium">{TYPE_LABELS[row.type]}</span>
                    <span className="text-muted-foreground text-xs">
                        {row.relation}
                    </span>
                </div>
            ),
        },
        {
            key: 'beneficiary_customer_id',
            header: 'Received by',
            render: (row) =>
                beneficiaryCell(
                    row.beneficiary_id,
                    row.beneficiary_customer_id,
                    row.beneficiary_name,
                ),
        },
        {
            key: 'source',
            header: 'From',
            render: (row) => (
                <div className="flex flex-col">
                    <span>{row.source}</span>
                    {row.base_amount && (
                        <span className="text-muted-foreground text-xs">
                            Base {inr(row.base_amount)}
                            {row.rate_percent &&
                                ` × ${Number(row.rate_percent)}%`}
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: 'amount',
            header: 'Amount',
            className: 'text-right',
            render: (row) => (
                <span
                    className={
                        row.status === 'skipped'
                            ? 'text-muted-foreground line-through'
                            : 'font-medium'
                    }
                >
                    {inr(row.amount)}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => (
                <div className="flex max-w-56 flex-col gap-0.5">
                    <Badge
                        variant={STATUS_VARIANT[row.status]}
                        className="w-fit"
                    >
                        {STATUS_LABELS[row.status]}
                    </Badge>
                    {row.reason && (
                        <span className="text-muted-foreground text-xs">
                            {row.reason}
                        </span>
                    )}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Upline Earnings" />

            <div className="flex w-full flex-col gap-4 p-4">
                <FilterBar
                    search={query}
                    onSearchChange={setQuery}
                    searchPlaceholder="Find a member by Customer ID, name or mobile"
                    onSubmit={findMember}
                    onReset={clearMember}
                />

                {!member && matches.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {matches.length === 20
                                    ? 'First 20 members matching'
                                    : `${matches.length} members match`}{' '}
                                “{search}” — pick one
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <DataTable
                                columns={[
                                    {
                                        key: 'customer_id',
                                        header: 'Customer ID',
                                    },
                                    {
                                        key: 'name',
                                        header: 'Name',
                                        render: (row) => row.name ?? '—',
                                    },
                                    {
                                        key: 'mobile',
                                        header: 'Mobile',
                                        render: (row) => row.mobile ?? '—',
                                    },
                                    {
                                        key: 'open',
                                        header: '',
                                        className: 'text-right',
                                        render: (row) => (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                asChild
                                            >
                                                <Link
                                                    href={uplineEarnings.url({
                                                        query: {
                                                            member: row.id,
                                                        },
                                                    })}
                                                >
                                                    Show earnings
                                                </Link>
                                            </Button>
                                        ),
                                    },
                                ]}
                                rows={matches}
                                rowKey={(row) => row.id}
                            />
                        </CardContent>
                    </Card>
                )}

                {!member && matches.length === 0 && (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-2 py-12 text-center">
                            {search ? (
                                <>
                                    <SearchX className="text-muted-foreground size-8" />
                                    <p className="font-medium">
                                        No member matches “{search}”.
                                    </p>
                                    <p className="text-muted-foreground text-sm">
                                        Check the Customer ID, name or mobile
                                        number and try again.
                                    </p>
                                </>
                            ) : (
                                <>
                                    <UserSearch className="text-muted-foreground size-8" />
                                    <p className="font-medium">
                                        Find a member to see what went up their
                                        network.
                                    </p>
                                    <p className="text-muted-foreground max-w-lg text-sm">
                                        Every Level Income, Purchase/Repurchase
                                        income, Store Profit share, Sponsor draw
                                        prize and Pair entry that this member's
                                        own payments and purchases gave to
                                        others — paid, held or skipped.
                                    </p>
                                </>
                            )}
                        </CardContent>
                    </Card>
                )}

                {member && totals && rows && (
                    <>
                        <Card>
                            <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2">
                                <CardTitle className="text-xl">
                                    {member.customer_id} — {member.name ?? '—'}
                                </CardTitle>
                                <div className="flex items-center gap-2">
                                    <Badge variant="secondary">
                                        {member.status}
                                    </Badge>
                                    <Button size="sm" variant="outline" asChild>
                                        <Link href={showMember.url(member.id)}>
                                            Member details
                                        </Link>
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent className="text-muted-foreground flex flex-wrap gap-x-6 gap-y-1 text-sm">
                                <span>Plan: {member.plan ?? '—'}</span>
                                <span>
                                    Sponsor: {member.sponsor_customer_id ?? '—'}
                                </span>
                            </CardContent>
                        </Card>

                        <StatStrip
                            icon={Network}
                            label="Paid to others"
                            value={inr(totals.paid)}
                            counts={[
                                {
                                    label: 'Held',
                                    value: inr(totals.held),
                                    color: 'amber',
                                },
                                {
                                    label: 'Skipped',
                                    value: totals.skipped,
                                    color: 'muted',
                                },
                                {
                                    label: 'Draw prizes',
                                    value: totals.prizes,
                                    color: 'purple',
                                },
                                {
                                    label: 'Pair entries',
                                    value: totals.pair_entries,
                                    color: 'teal',
                                },
                            ]}
                        />

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Who received what
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-3">
                                <DataTable
                                    columns={summaryColumns}
                                    rows={summary.receivers}
                                    rowKey={(row) => row.beneficiary_id}
                                    emptyMessage="Nobody has received anything from this member yet."
                                />
                                {summary.pair_only_count > 0 && (
                                    <p className="text-muted-foreground flex flex-wrap items-center gap-x-2 text-sm">
                                        + {summary.pair_only_count} more binary
                                        uplines got only a Pair entry.
                                        <button
                                            type="button"
                                            className="text-primary font-medium hover:underline"
                                            onClick={() => {
                                                setType('pair_entry');
                                                applyFilters({
                                                    type: 'pair_entry',
                                                });
                                            }}
                                        >
                                            Show Pair entries
                                        </button>
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Every entry
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        applyFilters();
                                    }}
                                    className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center"
                                >
                                    <Select
                                        value={type}
                                        onValueChange={setType}
                                    >
                                        <SelectTrigger className="w-full sm:w-52">
                                            <SelectValue placeholder="All Types" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={ANY}>
                                                All Types
                                            </SelectItem>
                                            {(
                                                Object.keys(
                                                    TYPE_LABELS,
                                                ) as RowType[]
                                            ).map((value) => (
                                                <SelectItem
                                                    key={value}
                                                    value={value}
                                                >
                                                    {TYPE_LABELS[value]}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    <Select
                                        value={status}
                                        onValueChange={setStatus}
                                    >
                                        <SelectTrigger className="w-full sm:w-40">
                                            <SelectValue placeholder="All Status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={ANY}>
                                                All Status
                                            </SelectItem>
                                            {(
                                                Object.keys(
                                                    STATUS_LABELS,
                                                ) as RowStatus[]
                                            ).map((value) => (
                                                <SelectItem
                                                    key={value}
                                                    value={value}
                                                >
                                                    {STATUS_LABELS[value]}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    <div className="flex items-center gap-2">
                                        <Label
                                            htmlFor="trace-from"
                                            className="sr-only"
                                        >
                                            From date
                                        </Label>
                                        <Input
                                            id="trace-from"
                                            type="date"
                                            value={from}
                                            max={to || undefined}
                                            onChange={(e) =>
                                                setFrom(e.target.value)
                                            }
                                            className="w-full sm:w-40"
                                            aria-label="From date"
                                        />
                                        <span className="text-muted-foreground text-sm">
                                            to
                                        </span>
                                        <Label
                                            htmlFor="trace-to"
                                            className="sr-only"
                                        >
                                            To date
                                        </Label>
                                        <Input
                                            id="trace-to"
                                            type="date"
                                            value={to}
                                            min={from || undefined}
                                            onChange={(e) =>
                                                setTo(e.target.value)
                                            }
                                            className="w-full sm:w-40"
                                            aria-label="To date"
                                        />
                                    </div>

                                    <div className="flex shrink-0 gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={resetFilters}
                                        >
                                            Reset
                                        </Button>
                                        <Button
                                            type="submit"
                                            variant="secondary"
                                        >
                                            Apply
                                        </Button>
                                    </div>
                                </form>

                                <DataTable
                                    columns={rowColumns}
                                    rows={rows.data}
                                    rowKey={(row) => row.key}
                                    emptyMessage={
                                        filters.type ||
                                        filters.status ||
                                        filters.from ||
                                        filters.to
                                            ? 'No entries match these filters.'
                                            : 'Nothing has gone up the network from this member yet.'
                                    }
                                />

                                <DataPagination
                                    paginated={rows}
                                    onPageChange={(page) =>
                                        applyFilters({ page })
                                    }
                                />
                            </CardContent>
                        </Card>
                    </>
                )}
            </div>
        </>
    );
}

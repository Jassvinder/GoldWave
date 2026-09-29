import { Head, router } from '@inertiajs/react';
import {
    ArrowDownToLine,
    ArrowUpFromLine,
    Calculator,
    Gem,
    Landmark,
    Scale,
    Store as StoreIcon,
    Trophy,
    Wallet,
} from 'lucide-react';
import { type FormEventHandler, type ReactNode, useState } from 'react';
import { FormSection } from '@/components/form-section';
import { StatCard, StatGrid } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn, formatDate } from '@/lib/utils';
import { index } from '@/routes/super-admin/financial-summary';

type Metal = 'gold' | 'silver';

type Summary = {
    rates: Record<Metal, number | null>;
    collections: {
        total: number;
        count: number;
        by_type: Record<string, number>;
        by_mode: Record<string, number>;
        by_metal: Record<Metal, number>;
    };
    earnings: {
        total: number;
        by_category: {
            category: string;
            label: string;
            count: number;
            amount: number;
        }[];
    };
    payouts: {
        processed_count: number;
        gross: number;
        tds: number;
        fee: number;
        net_paid: number;
        pending_count: number;
        pending_amount: number;
    };
    positions: {
        member_wallets: number;
        member_on_hold: number;
        store_wallets: number;
        company_wallet: number;
        emis_to_collect: number;
        emis_to_collect_count: number;
    };
    jewellery: Record<
        Metal,
        {
            entitlements: number;
            delivered: number;
            pending: number;
            delivered_grams: number;
            pending_grams: number;
            delivered_commitment: number;
            pending_commitment: number;
            delivered_cost: number;
            pending_cost: number;
        }
    >;
    draws: Record<
        Metal,
        { draws: number; upline_benefits: number; value: number }
    >;
    store: Record<
        Metal,
        {
            sales_count: number;
            sales_grams: number;
            sales_amount: number;
            sales_gst: number;
            buyback_count: number;
            buyback_grams: number;
            buyback_amount: number;
            stock_pieces: number;
            stock_grams: number;
        }
    >;
    estimate: {
        collected: number;
        to_collect: number;
        expected_income: number;
        member_earnings: number;
        jewellery_cost: number;
        draw_cost: number;
        total_cost: number;
        surplus: number;
        /** Positive = metal got dearer since booking (a cost), negative = it got cheaper. */
        rate_impact: number;
        surplus_at_booking_rates: number;
    };
};

type Props = {
    summary: Summary;
    filters: { from: string | null; to: string | null };
};

const METALS: Metal[] = ['gold', 'silver'];
const METAL_LABEL: Record<Metal, string> = { gold: 'Gold', silver: 'Silver' };

const inr = (value: number) =>
    `₹${value.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const grams = (value: number) =>
    `${value.toLocaleString('en-IN', { maximumFractionDigits: 3 })} g`;

const count = (value: number) => value.toLocaleString('en-IN');

/** Label / value rows inside a section. */
function Rows({
    rows,
}: {
    rows: { label: ReactNode; value: ReactNode; strong?: boolean }[];
}) {
    return (
        <dl className="divide-y text-sm">
            {rows.map((row, i) => (
                <div
                    key={i}
                    className={cn(
                        'flex items-center justify-between gap-4 py-2',
                        row.strong && 'font-semibold',
                    )}
                >
                    <dt
                        className={
                            row.strong ? undefined : 'text-muted-foreground'
                        }
                    >
                        {row.label}
                    </dt>
                    <dd className="text-right tabular-nums">{row.value}</dd>
                </div>
            ))}
        </dl>
    );
}

/** A small per-metal comparison table. */
function MetalTable({
    rows,
}: {
    rows: { label: string; gold: ReactNode; silver: ReactNode }[];
}) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="text-muted-foreground border-b text-left text-xs uppercase">
                        <th className="py-2 pr-3 font-medium" />
                        {METALS.map((metal) => (
                            <th
                                key={metal}
                                className="py-2 pl-3 text-right font-medium"
                            >
                                {METAL_LABEL[metal]}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {rows.map((row) => (
                        <tr key={row.label}>
                            <td className="text-muted-foreground py-2 pr-3">
                                {row.label}
                            </td>
                            <td className="py-2 pl-3 text-right tabular-nums">
                                {row.gold}
                            </td>
                            <td className="py-2 pl-3 text-right tabular-nums">
                                {row.silver}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/**
 * T-172 (28-09-2026) — first version of the company-wide money and metal
 * summary. The user allowed assumptions for this page; they are listed in the
 * estimate section and in Docs/INSTRUCTIONS.md so they can be corrected.
 */
export default function FinancialSummary({ summary, filters }: Props) {
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');
    const s = summary;
    const filtered = Boolean(filters.from || filters.to);
    const periodLabel = filtered
        ? `${filters.from ? formatDate(filters.from) : 'start'} to ${filters.to ? formatDate(filters.to) : 'today'}`
        : 'All time';

    const apply: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(
            index.url(),
            { from: from || undefined, to: to || undefined },
            { preserveState: true },
        );
    };

    const reset = () => {
        setFrom('');
        setTo('');
        router.get(index.url(), {}, { preserveState: true });
    };

    return (
        <>
            <Head title="Financial Summary" />

            <div className="flex w-full flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Financial Summary
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            Money in, member earnings, payouts and metal —
                            period: <strong>{periodLabel}</strong>. Balances,
                            jewellery owed and the estimate are always as of
                            today.
                        </p>
                    </div>
                    <form
                        onSubmit={apply}
                        className="flex flex-wrap items-end gap-2"
                    >
                        <div className="grid gap-1">
                            <Label htmlFor="from" className="text-xs">
                                From
                            </Label>
                            <Input
                                id="from"
                                type="date"
                                value={from}
                                onChange={(e) => setFrom(e.target.value)}
                                className="w-40"
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="to" className="text-xs">
                                To
                            </Label>
                            <Input
                                id="to"
                                type="date"
                                value={to}
                                onChange={(e) => setTo(e.target.value)}
                                className="w-40"
                            />
                        </div>
                        <Button type="submit">Apply</Button>
                        {filtered && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={reset}
                            >
                                Reset
                            </Button>
                        )}
                    </form>
                </div>

                <StatGrid>
                    <StatCard
                        icon={ArrowDownToLine}
                        color="green"
                        label="Money Collected"
                        value={inr(s.collections.total)}
                        stats={[
                            {
                                label: 'Payments',
                                value: count(s.collections.count),
                            },
                        ]}
                    />
                    <StatCard
                        icon={Wallet}
                        color="amber"
                        label="Member Earnings Credited"
                        value={inr(s.earnings.total)}
                    />
                    <StatCard
                        icon={ArrowUpFromLine}
                        color="blue"
                        label="Payouts Paid (net)"
                        value={inr(s.payouts.net_paid)}
                        stats={[
                            {
                                label: 'Pending',
                                value: inr(s.payouts.pending_amount),
                            },
                        ]}
                    />
                    <StatCard
                        icon={Scale}
                        color={s.estimate.surplus >= 0 ? 'teal' : 'red'}
                        label="Estimated Surplus (all time)"
                        value={inr(s.estimate.surplus)}
                    />
                </StatGrid>

                <div className="grid gap-6 lg:grid-cols-2">
                    <FormSection
                        icon={ArrowDownToLine}
                        color="green"
                        title="Money In"
                        description="Paid registration and EMI payments. Store sale money is collected by the store outside the app, so it is not counted here."
                    >
                        <Rows
                            rows={[
                                {
                                    label: 'Registrations',
                                    value: inr(
                                        s.collections.by_type.registration ?? 0,
                                    ),
                                },
                                {
                                    label: 'EMI installments',
                                    value: inr(
                                        s.collections.by_type.emi_installment ??
                                            0,
                                    ),
                                },
                                {
                                    label: 'Cash',
                                    value: inr(s.collections.by_mode.cash ?? 0),
                                },
                                {
                                    label: 'Online',
                                    value: inr(
                                        s.collections.by_mode.online ?? 0,
                                    ),
                                },
                                {
                                    label: 'Wallet (assisted registration)',
                                    value: inr(
                                        s.collections.by_mode.wallet ?? 0,
                                    ),
                                },
                                {
                                    label: 'Gold plans',
                                    value: inr(s.collections.by_metal.gold),
                                },
                                {
                                    label: 'Silver plans',
                                    value: inr(s.collections.by_metal.silver),
                                },
                                {
                                    label: 'Total collected',
                                    value: inr(s.collections.total),
                                    strong: true,
                                },
                            ]}
                        />
                    </FormSection>

                    <FormSection
                        icon={Wallet}
                        color="amber"
                        title="Member Earnings"
                        description="Every income credited to member wallets in the period."
                    >
                        <Rows
                            rows={[
                                ...s.earnings.by_category.map((row) => ({
                                    label: `${row.label} (${count(row.count)})`,
                                    value: inr(row.amount),
                                })),
                                {
                                    label: 'Total earnings',
                                    value: inr(s.earnings.total),
                                    strong: true,
                                },
                            ]}
                        />
                    </FormSection>

                    <FormSection
                        icon={ArrowUpFromLine}
                        color="blue"
                        title="Payouts"
                        description="Withdrawals processed by Super Admin in the period."
                    >
                        <Rows
                            rows={[
                                {
                                    label: `Processed (${count(s.payouts.processed_count)})`,
                                    value: inr(s.payouts.gross),
                                },
                                {
                                    label: 'TDS withheld',
                                    value: inr(s.payouts.tds),
                                },
                                {
                                    label: 'Processing fees',
                                    value: inr(s.payouts.fee),
                                },
                                {
                                    label: 'Net paid to members',
                                    value: inr(s.payouts.net_paid),
                                    strong: true,
                                },
                                {
                                    label: `Pending requests (${count(s.payouts.pending_count)}) — today`,
                                    value: inr(s.payouts.pending_amount),
                                },
                            ]}
                        />
                    </FormSection>

                    <FormSection
                        icon={Landmark}
                        color="purple"
                        title="Balances Today"
                        description="What is sitting in wallets and still to be collected, as of today."
                    >
                        <Rows
                            rows={[
                                {
                                    label: 'Member wallets (owed to members)',
                                    value: inr(s.positions.member_wallets),
                                },
                                {
                                    label: 'Of which on hold for payouts',
                                    value: inr(s.positions.member_on_hold),
                                },
                                {
                                    label: 'Store wallets (advances)',
                                    value: inr(s.positions.store_wallets),
                                },
                                {
                                    label: 'Company wallet',
                                    value: inr(s.positions.company_wallet),
                                },
                                {
                                    label: `EMIs still to collect (${count(s.positions.emis_to_collect_count)})`,
                                    value: inr(s.positions.emis_to_collect),
                                },
                            ]}
                        />
                    </FormSection>
                </div>

                <FormSection
                    icon={Gem}
                    color="amber"
                    title="Plan Jewellery — Metal Owed and Delivered"
                    description="As of today. Fixed-weight entitlements (Current Rate bookings and one-time plans) are valued at today's rate; Future Rate schedules owe their rupee commitment."
                >
                    <MetalTable
                        rows={[
                            {
                                label: "Today's rate (per 10 gm)",
                                gold:
                                    s.rates.gold !== null
                                        ? inr(s.rates.gold * 10)
                                        : '—',
                                silver:
                                    s.rates.silver !== null
                                        ? inr(s.rates.silver * 10)
                                        : '—',
                            },
                            {
                                label: 'Members entitled',
                                gold: count(s.jewellery.gold.entitlements),
                                silver: count(s.jewellery.silver.entitlements),
                            },
                            {
                                label: 'Delivered',
                                gold: `${count(s.jewellery.gold.delivered)} · ${grams(s.jewellery.gold.delivered_grams)}`,
                                silver: `${count(s.jewellery.silver.delivered)} · ${grams(s.jewellery.silver.delivered_grams)}`,
                            },
                            {
                                label: 'Still to deliver',
                                gold: count(s.jewellery.gold.pending),
                                silver: count(s.jewellery.silver.pending),
                            },
                            {
                                label: 'Fixed weight still owed',
                                gold: grams(s.jewellery.gold.pending_grams),
                                silver: grams(s.jewellery.silver.pending_grams),
                            },
                            {
                                label: 'Future Rate value still owed',
                                gold: inr(s.jewellery.gold.pending_commitment),
                                silver: inr(
                                    s.jewellery.silver.pending_commitment,
                                ),
                            },
                            {
                                label: 'Estimated cost still owed',
                                gold: inr(s.jewellery.gold.pending_cost),
                                silver: inr(s.jewellery.silver.pending_cost),
                            },
                            {
                                label: 'Estimated cost already delivered',
                                gold: inr(s.jewellery.gold.delivered_cost),
                                silver: inr(s.jewellery.silver.delivered_cost),
                            },
                        ]}
                    />
                </FormSection>

                <div className="grid gap-6 lg:grid-cols-2">
                    <FormSection
                        icon={StoreIcon}
                        color="blue"
                        title="Store Metal"
                        description="Sales and buybacks in the period; stock as of today. Store money stays with the store."
                    >
                        <MetalTable
                            rows={[
                                {
                                    label: 'Sold',
                                    gold: `${count(s.store.gold.sales_count)} · ${grams(s.store.gold.sales_grams)}`,
                                    silver: `${count(s.store.silver.sales_count)} · ${grams(s.store.silver.sales_grams)}`,
                                },
                                {
                                    label: 'Sales amount',
                                    gold: inr(s.store.gold.sales_amount),
                                    silver: inr(s.store.silver.sales_amount),
                                },
                                {
                                    label: 'GST on sales',
                                    gold: inr(s.store.gold.sales_gst),
                                    silver: inr(s.store.silver.sales_gst),
                                },
                                {
                                    label: 'Bought back',
                                    gold: `${count(s.store.gold.buyback_count)} · ${grams(s.store.gold.buyback_grams)}`,
                                    silver: `${count(s.store.silver.buyback_count)} · ${grams(s.store.silver.buyback_grams)}`,
                                },
                                {
                                    label: 'Buyback paid',
                                    gold: inr(s.store.gold.buyback_amount),
                                    silver: inr(s.store.silver.buyback_amount),
                                },
                                {
                                    label: 'In store stock today',
                                    gold: `${count(s.store.gold.stock_pieces)} pcs · ${grams(s.store.gold.stock_grams)}`,
                                    silver: `${count(s.store.silver.stock_pieces)} pcs · ${grams(s.store.silver.stock_grams)}`,
                                },
                            ]}
                        />
                    </FormSection>

                    <FormSection
                        icon={Trophy}
                        color="purple"
                        title="Monthly Draw Prizes"
                        description="Executed draws in the period, at the configured prize value — counted twice when the winner's sponsor also qualifies."
                    >
                        <MetalTable
                            rows={[
                                {
                                    label: 'Draws',
                                    gold: count(s.draws.gold.draws),
                                    silver: count(s.draws.silver.draws),
                                },
                                {
                                    label: 'Sponsor benefits',
                                    gold: count(s.draws.gold.upline_benefits),
                                    silver: count(
                                        s.draws.silver.upline_benefits,
                                    ),
                                },
                                {
                                    label: 'Prize value',
                                    gold: inr(s.draws.gold.value),
                                    silver: inr(s.draws.silver.value),
                                },
                            ]}
                        />
                    </FormSection>
                </div>

                <FormSection
                    icon={Calculator}
                    color={s.estimate.surplus >= 0 ? 'teal' : 'red'}
                    title="Estimated Company Position (all time, as of today)"
                    description="A first estimate to review — not an accounting statement."
                >
                    <div className="grid gap-6 lg:grid-cols-2">
                        <Rows
                            rows={[
                                {
                                    label: 'Collected so far',
                                    value: inr(s.estimate.collected),
                                },
                                {
                                    label: 'EMIs still to collect',
                                    value: inr(s.estimate.to_collect),
                                },
                                {
                                    label: 'Expected income',
                                    value: inr(s.estimate.expected_income),
                                    strong: true,
                                },
                                {
                                    label: 'Member earnings credited',
                                    value: `− ${inr(s.estimate.member_earnings)}`,
                                },
                                {
                                    label: 'Plan jewellery (delivered + owed)',
                                    value: `− ${inr(s.estimate.jewellery_cost)}`,
                                },
                                {
                                    label: 'Draw prizes',
                                    value: `− ${inr(s.estimate.draw_cost)}`,
                                },
                                {
                                    label: 'Estimated surplus',
                                    value: (
                                        <span
                                            className={
                                                s.estimate.surplus >= 0
                                                    ? 'text-emerald-600 dark:text-emerald-400'
                                                    : 'text-destructive'
                                            }
                                        >
                                            {inr(s.estimate.surplus)}
                                        </span>
                                    ),
                                    strong: true,
                                },
                                {
                                    label: (
                                        <span>
                                            Of which: metal rate change
                                            <span className="block text-xs">
                                                fixed-weight jewellery at
                                                today&apos;s rate vs the rate it
                                                was booked at
                                            </span>
                                        </span>
                                    ),
                                    value: (
                                        <span
                                            className={
                                                s.estimate.rate_impact > 0
                                                    ? 'text-destructive'
                                                    : 'text-emerald-600 dark:text-emerald-400'
                                            }
                                        >
                                            {s.estimate.rate_impact > 0
                                                ? `− ${inr(s.estimate.rate_impact)}`
                                                : `+ ${inr(Math.abs(s.estimate.rate_impact))}`}
                                        </span>
                                    ),
                                },
                                {
                                    label: 'Surplus if metal was bought at booking rates',
                                    value: inr(
                                        s.estimate.surplus_at_booking_rates,
                                    ),
                                },
                            ]}
                        />
                        <div className="bg-muted/50 rounded-lg p-4 text-sm">
                            <p className="mb-2 font-medium">
                                How this is estimated
                            </p>
                            <ul className="text-muted-foreground list-disc space-y-1 pl-4">
                                <li>
                                    Income = paid registrations and EMIs, plus
                                    every EMI still unpaid (assumes all will be
                                    paid).
                                </li>
                                <li>
                                    Member earnings = everything credited to
                                    member wallets, paid out or not.
                                </li>
                                <li>
                                    Current Rate and one-time plans owe a fixed
                                    weight, valued at today&apos;s rate — so
                                    this moves with the metal price.
                                </li>
                                <li>
                                    &quot;Metal rate change&quot; shows how much of
                                    that is only because the rate moved since
                                    booking. If the metal was bought on the
                                    booking day, this loss (or gain) does not
                                    really happen — see the last line.
                                </li>
                                <li>
                                    Future Rate plans owe their rupee
                                    commitment.
                                </li>
                                <li>
                                    Not included yet: making/hallmark charges,
                                    GST, store operations, and business expenses
                                    outside the app.
                                </li>
                            </ul>
                        </div>
                    </div>
                </FormSection>
            </div>
        </>
    );
}

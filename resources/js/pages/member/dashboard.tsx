import { Head, Link } from '@inertiajs/react';
import {
    Award,
    Dices,
    Gem,
    Network,
    Receipt,
    Trophy,
    Users,
    WalletCards,
} from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { StatCard, StatGrid } from '@/components/stat-card';
import { index as boosterIndex } from '@/routes/member/booster';
import { show as showDirects } from '@/routes/member/directs';
import { index as drawIndex } from '@/routes/member/draw';
import { index as emiIndex } from '@/routes/member/emi';
import { index as levelIncomeIndex } from '@/routes/member/level-income';
import { index as pairRewardIndex } from '@/routes/member/pair-reward';
import { show as showTree } from '@/routes/member/tree';
import { index as walletIndex } from '@/routes/member/wallet';

type PairSide = {
    team: number;
    unused: number;
    consumed: number;
    awaiting: number;
    inactive: number;
    dummy: number;
};

/** Team members that haven't produced a pair entry yet — EMI qualification pending, inactive, or an unassigned dummy. */
const notYetEligible = (side: PairSide) =>
    side.awaiting + side.inactive + side.dummy;

type Props = {
    member: {
        customer_id: string;
        name: string | null;
        status: string;
        activated_at: string | null;
    };
    plan: { name: string; is_emi_plan: boolean } | null;
    emi: { paid_installments: number; total_installments: number } | null;
    wallet: {
        balance: string;
        on_hold: string;
        withdrawn: string;
        used_for_registrations: string;
    };
    direct_count: number;
    team: { direct: number; left: number; right: number; total: number };
    income: {
        total: string;
        level_income: string;
        pair_reward: string;
        booster: string;
        purchase_repurchase: string;
        store_profit: string;
        held_level_income: string;
        held_emi_overdue: string;
    };
    pair: { left: PairSide; right: PairSide; milestones_achieved: number };
    booster_active_levels: number;
    booster_levels: {
        level_no: number;
        received: string;
        months_paid: number;
        months_total: number;
    }[];
    draw_active: boolean;
    draw: {
        wins: {
            group_no: number;
            cycle_month_no: number;
            executed_at: string | null;
            prize_name: string | null;
            prize_value: string | null;
        }[];
        won: number;
        upline_benefits: number;
        latest_upline: {
            prize_name: string | null;
            prize_value: string | null;
            winner_customer_id: string | null;
        } | null;
    };
    alerts: string[];
};

/** Indian-grouped rupee amount, e.g. ₹14,500.00. */
const inr = (value: string | number) =>
    `₹${Number(value).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const isZero = (value: string | number) => Number(value) === 0;

/** A breakdown row that greys itself out while nothing has been earned from that source. */
const moneyRow = (label: string, value: string) => ({
    label,
    value: inr(value),
    muted: isZero(value),
});

const plural = (count: number, word: string) =>
    `${count} ${word}${count === 1 ? '' : 's'}`;

/**
 * INSTRUCTIONS.md M01 — Dashboard. Money cards come first and are itemised so the
 * member can see exactly which incomes make up each figure (30-09-2026): Total
 * Income Earned lists every income type and always equals what was credited to
 * the wallet; Wallet Balance shows that total minus what was withdrawn or spent.
 */
export default function Dashboard({
    member,
    plan,
    emi,
    wallet,
    direct_count,
    team,
    income,
    pair,
    booster_active_levels,
    booster_levels,
    draw_active,
    draw,
    alerts,
}: Props) {
    const notInWallet = [
        {
            amount: income.held_level_income,
            reason: 'Level Income waiting for your direct-member requirement',
            href: levelIncomeIndex(),
        },
        {
            amount: income.held_emi_overdue,
            reason: 'held until your overdue EMI is paid',
            href: emiIndex(),
        },
    ].filter((item) => !isZero(item.amount));

    const remainingInstallments = emi
        ? emi.total_installments - emi.paid_installments
        : 0;

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex w-full flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Welcome, {member.name ?? member.customer_id}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Customer ID {member.customer_id} ·{' '}
                        <Badge variant="secondary">{member.status}</Badge>
                    </p>
                </div>

                {alerts.map((alert, index) => (
                    <Alert key={index}>
                        <AlertDescription>{alert}</AlertDescription>
                    </Alert>
                ))}

                <section className="flex flex-col gap-3">
                    <h2 className="text-lg font-semibold">My Earnings</h2>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <StatCard
                            icon={Trophy}
                            color="teal"
                            label="Total Income Earned"
                            value={inr(income.total)}
                            description="All income credited to your wallet so far, by type."
                            breakdown={[
                                moneyRow('Level Income', income.level_income),
                                moneyRow('Pair/Reward', income.pair_reward),
                                moneyRow('Income Booster', income.booster),
                                moneyRow(
                                    'Purchase/Repurchase Income',
                                    income.purchase_repurchase,
                                ),
                                moneyRow(
                                    'Store Profit Share',
                                    income.store_profit,
                                ),
                            ]}
                            footer={
                                notInWallet.length > 0 && (
                                    <div className="rounded-md bg-amber-50 p-2 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                        <p className="font-medium">
                                            Earned, not yet in your wallet
                                        </p>
                                        <ul className="mt-1 flex flex-col gap-0.5">
                                            {notInWallet.map((item) => (
                                                <li key={item.reason}>
                                                    <Link
                                                        href={item.href}
                                                        className="underline-offset-2 hover:underline"
                                                    >
                                                        <span className="font-semibold tabular-nums">
                                                            {inr(item.amount)}
                                                        </span>{' '}
                                                        — {item.reason}
                                                    </Link>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                )
                            }
                        />

                        <StatCard
                            icon={WalletCards}
                            color="green"
                            label="Wallet Balance"
                            value={inr(wallet.balance)}
                            description="Money you can withdraw as a Payout or use to register new members."
                            breakdown={[
                                {
                                    label: 'Total income credited',
                                    value: inr(income.total),
                                },
                                {
                                    label: 'Withdrawn (Payouts)',
                                    value: `− ${inr(wallet.withdrawn)}`,
                                    muted: isZero(wallet.withdrawn),
                                },
                                {
                                    label: 'Used for member registrations',
                                    value: `− ${inr(wallet.used_for_registrations)}`,
                                    muted: isZero(
                                        wallet.used_for_registrations,
                                    ),
                                },
                            ]}
                            footer={
                                !isZero(wallet.on_hold) && (
                                    <p className="text-muted-foreground text-xs">
                                        {inr(wallet.on_hold)} of this balance is
                                        reserved for a Payout request in
                                        progress.
                                    </p>
                                )
                            }
                            href={walletIndex()}
                        />

                        <StatCard
                            icon={Award}
                            color="red"
                            label="Pair/Reward Income"
                            value={inr(income.pair_reward)}
                            description="Reward paid each time your Left and Right teams complete a milestone."
                            breakdown={[
                                {
                                    label: 'Milestones achieved',
                                    value: pair.milestones_achieved,
                                },
                                {
                                    label: 'Entries ready for next milestone',
                                    value: `${pair.left.unused} Left · ${pair.right.unused} Right`,
                                },
                                {
                                    label: 'Entries already used',
                                    value: `${pair.left.consumed} Left · ${pair.right.consumed} Right`,
                                    muted: true,
                                },
                                {
                                    label: 'Team members not yet eligible',
                                    value: `${notYetEligible(pair.left)} Left · ${notYetEligible(pair.right)} Right`,
                                    muted: true,
                                },
                            ]}
                            href={pairRewardIndex()}
                        />
                    </div>
                </section>

                <section className="flex flex-col gap-3">
                    <h2 className="text-lg font-semibold">
                        My Membership &amp; Team
                    </h2>
                    <StatGrid>
                        <StatCard
                            icon={Gem}
                            color="purple"
                            label="Membership Plan"
                            value={plan?.name ?? 'No active plan'}
                            description={
                                plan
                                    ? plan.is_emi_plan
                                        ? 'Paid in monthly EMI installments.'
                                        : 'Paid in full (one-time).'
                                    : undefined
                            }
                        />

                        <StatCard
                            icon={Receipt}
                            color="amber"
                            label="EMI Installments Paid"
                            value={
                                emi
                                    ? `${emi.paid_installments} of ${emi.total_installments}`
                                    : 'No EMI schedule'
                            }
                            description={
                                emi
                                    ? remainingInstallments > 0
                                        ? `${plural(remainingInstallments, 'installment')} remaining.`
                                        : 'All installments paid.'
                                    : undefined
                            }
                            href={emiIndex()}
                        />

                        <StatCard
                            icon={Users}
                            color="blue"
                            label="Direct Members"
                            value={direct_count}
                            description="Members who joined with you as their sponsor."
                            href={showDirects()}
                        />

                        <StatCard
                            icon={Network}
                            color="teal"
                            label="Total Team"
                            value={team.total}
                            description="Everyone placed below you in your Left and Right teams."
                            breakdown={[
                                { label: 'Left team', value: team.left },
                                { label: 'Right team', value: team.right },
                            ]}
                            href={showTree()}
                        />

                        <StatCard
                            icon={Award}
                            color="purple"
                            label="Income Booster"
                            value={
                                booster_active_levels > 0
                                    ? `${plural(booster_active_levels, 'level')} active`
                                    : 'Not active'
                            }
                            description={
                                booster_active_levels > 0
                                    ? 'Booster levels still paying you monthly income.'
                                    : 'No Booster level is paying you right now.'
                            }
                            // 01-10-2026 — what each qualified level has paid so far, plus the total.
                            breakdown={
                                booster_levels.length > 0
                                    ? [
                                          ...booster_levels.map((level) => ({
                                              label: `Level ${level.level_no} · ${level.months_paid} of ${level.months_total} months`,
                                              value: inr(level.received),
                                              muted: isZero(level.received),
                                          })),
                                          ...(booster_levels.length > 1
                                              ? [
                                                    {
                                                        label: 'Total received',
                                                        value: inr(
                                                            booster_levels.reduce(
                                                                (sum, level) =>
                                                                    sum +
                                                                    Number(
                                                                        level.received,
                                                                    ),
                                                                0,
                                                            ),
                                                        ),
                                                    },
                                                ]
                                              : []),
                                      ]
                                    : undefined
                            }
                            href={boosterIndex()}
                        />

                        <StatCard
                            icon={Dices}
                            color="blue"
                            label="Monthly Draw"
                            value={draw_active ? 'Active' : 'Not active'}
                            description={
                                draw_active
                                    ? 'You are in an active group for the monthly draw.'
                                    : 'You are not in an active draw group right now.'
                            }
                            breakdown={[
                                {
                                    label: 'Draws won',
                                    value: draw.won,
                                    muted: draw.won === 0,
                                },
                                // 01-10-2026 — each win with the jewellery and its value.
                                ...draw.wins.map((win) => ({
                                    label: `Won · Group #${win.group_no}, Month ${win.cycle_month_no}`,
                                    value: win.prize_name
                                        ? `${win.prize_name} · ${inr(win.prize_value ?? 0)}`
                                        : '—',
                                })),
                                {
                                    // T-199 — prizes received because a direct member won (Sponsor with 10+ directs).
                                    label: 'Upline benefits received',
                                    value: draw.upline_benefits,
                                    muted: draw.upline_benefits === 0,
                                },
                            ]}
                            footer={
                                draw.latest_upline && (
                                    <p className="rounded-md bg-amber-50 p-2 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                        Latest upline benefit:{' '}
                                        {draw.latest_upline.prize_name}
                                        {draw.latest_upline.prize_value &&
                                            ` · ₹${Number(draw.latest_upline.prize_value).toLocaleString('en-IN')}`}{' '}
                                        — your direct{' '}
                                        {draw.latest_upline.winner_customer_id}{' '}
                                        won the draw.
                                    </p>
                                )
                            }
                            href={drawIndex()}
                        />
                    </StatGrid>
                </section>
            </div>
        </>
    );
}

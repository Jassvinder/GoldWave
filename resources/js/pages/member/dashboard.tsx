import { Head } from '@inertiajs/react';
import {
    Award,
    Dices,
    Gem,
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
import { index as pairRewardIndex } from '@/routes/member/pair-reward';
import { index as walletIndex } from '@/routes/member/wallet';

type Props = {
    member: {
        customer_id: string;
        name: string | null;
        status: string;
        activated_at: string | null;
    };
    plan: { name: string; is_emi_plan: boolean } | null;
    emi: { paid_installments: number; total_installments: number } | null;
    wallet_balance: string;
    direct_count: number;
    income: { level_income: string; purchase_repurchase: string };
    pair: { unused_left: number; unused_right: number };
    booster_active_levels: number;
    draw_active: boolean;
    alerts: string[];
};

/** INSTRUCTIONS.md M01 — Dashboard: profile/plan/EMI/income/team/draw/booster summary, every value linking to its source module. */
export default function Dashboard({
    member,
    plan,
    emi,
    wallet_balance,
    direct_count,
    income,
    pair,
    booster_active_levels,
    draw_active,
    alerts,
}: Props) {
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

                <StatGrid>
                    <StatCard
                        icon={Gem}
                        color="purple"
                        label="Membership Plan"
                        value={plan?.name ?? 'No active plan'}
                    />

                    <StatCard
                        icon={Receipt}
                        color="amber"
                        label="EMI Schedule"
                        value={
                            emi
                                ? `${emi.paid_installments} / ${emi.total_installments}`
                                : 'No EMI schedule'
                        }
                        stats={
                            emi
                                ? [
                                      {
                                          label: 'Installments paid',
                                          value: emi.paid_installments,
                                      },
                                  ]
                                : undefined
                        }
                        href={emiIndex()}
                    />

                    <StatCard
                        icon={WalletCards}
                        color="green"
                        label="Wallet Balance"
                        value={`₹${wallet_balance}`}
                        href={walletIndex()}
                    />

                    <StatCard
                        icon={Users}
                        color="blue"
                        label="My Team — Direct Members"
                        value={direct_count}
                        href={showDirects()}
                    />

                    <StatCard
                        icon={Trophy}
                        color="teal"
                        label="Income Summary"
                        value={`₹${income.level_income}`}
                        stats={[
                            {
                                label: 'Purchase/Repurchase',
                                value: `₹${income.purchase_repurchase}`,
                            },
                        ]}
                    />

                    <StatCard
                        icon={Award}
                        color="red"
                        label="Pair/Reward — Unused"
                        value={`${pair.unused_left}L / ${pair.unused_right}R`}
                        href={pairRewardIndex()}
                    />

                    <StatCard
                        icon={Award}
                        color="purple"
                        label="Income Booster"
                        value={booster_active_levels}
                        stats={[
                            {
                                label:
                                    'Active level' +
                                    (booster_active_levels === 1 ? '' : 's'),
                                value: booster_active_levels,
                            },
                        ]}
                        href={boosterIndex()}
                    />

                    <StatCard
                        icon={Dices}
                        color="blue"
                        label="Monthly Draw"
                        value={draw_active ? 'Active' : 'Not active'}
                        stats={[
                            {
                                label: 'Status',
                                value: draw_active
                                    ? 'In an active draw group'
                                    : 'No active draw group',
                            },
                        ]}
                        href={drawIndex()}
                    />
                </StatGrid>
            </div>
        </>
    );
}

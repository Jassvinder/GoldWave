import { Head } from '@inertiajs/react';
import {
    Banknote,
    Coins,
    Dices,
    Gift,
    Receipt,
    ShieldCheck,
    Store as StoreIcon,
    Users,
} from 'lucide-react';
import { StatCard, StatGrid } from '@/components/stat-card';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/lib/utils';

type Props = {
    members: { total: number; active: number; pending: number; today: number };
    payments: { paid: number; pending: number; failed: number };
    emi: { due: number; overdue: number };
    income: { level_income: string; pair_reward: string; booster: string };
    payouts: { pending: number; processed: number };
    draw: {
        active_groups: number;
        last_winner_customer_id: string | null;
        last_executed_at: string | null;
    };
    dummy_entries: {
        enabled: boolean;
        daily_count: number;
        unassigned: number;
    };
    store: {
        sales_total: string;
        sales_count: number;
        distribution_total: string;
    };
    alerts: string[];
};

/** INSTRUCTIONS.md S01 / "Admin Dashboard — What Appears" — global health, business controls, queues, alerts. */
export default function SuperAdminDashboard({
    members,
    payments,
    emi,
    income,
    payouts,
    draw,
    dummy_entries,
    store,
    alerts,
}: Props) {
    return (
        <>
            <Head title="System Dashboard" />

            <div className="flex w-full flex-col gap-6 p-4">
                {alerts.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Alerts</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2">
                            {alerts.map((alert, index) => (
                                <p
                                    key={index}
                                    className="text-destructive text-sm"
                                >
                                    {alert}
                                </p>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <StatGrid>
                    <StatCard
                        icon={Users}
                        color="blue"
                        label="Total Members"
                        value={members.total}
                        stats={[
                            { label: 'Active', value: members.active },
                            { label: 'Pending', value: members.pending },
                            { label: 'Today', value: members.today },
                        ]}
                    />

                    <StatCard
                        icon={Banknote}
                        color="green"
                        label="Payment In"
                        value={payments.paid}
                        stats={[
                            { label: 'Pending', value: payments.pending },
                            { label: 'Failed', value: payments.failed },
                        ]}
                    />

                    <StatCard
                        icon={Receipt}
                        color="amber"
                        label="EMI"
                        value={emi.due}
                        stats={[
                            { label: 'Due', value: emi.due },
                            { label: 'Overdue', value: emi.overdue },
                        ]}
                    />

                    <StatCard
                        icon={Coins}
                        color="purple"
                        label="Income Generated"
                        value={`₹${income.level_income}`}
                        stats={[
                            {
                                label: 'Pair/Reward',
                                value: `₹${income.pair_reward}`,
                            },
                            { label: 'Booster', value: `₹${income.booster}` },
                        ]}
                    />

                    <StatCard
                        icon={ShieldCheck}
                        color="red"
                        label="Payouts"
                        value={payouts.pending}
                        stats={[
                            { label: 'Pending', value: payouts.pending },
                            { label: 'Processed', value: payouts.processed },
                        ]}
                    />

                    <StatCard
                        icon={Dices}
                        color="blue"
                        label="Monthly Draw"
                        value={draw.active_groups}
                        stats={[
                            {
                                label: 'Last Winner',
                                value: draw.last_winner_customer_id ?? '—',
                            },
                            {
                                label: 'Last Executed',
                                value: formatDate(draw.last_executed_at),
                            },
                        ]}
                    />

                    <StatCard
                        icon={Gift}
                        color="teal"
                        label="Daily Dummy Entries"
                        value={dummy_entries.enabled ? 'Enabled' : 'Disabled'}
                        stats={[
                            {
                                label: 'Daily Count',
                                value: dummy_entries.daily_count,
                            },
                            {
                                label: 'Unassigned',
                                value: dummy_entries.unassigned,
                            },
                        ]}
                    />

                    <StatCard
                        icon={StoreIcon}
                        color="purple"
                        label="Store Network"
                        value={store.sales_count}
                        stats={[
                            {
                                label: 'Sales Total',
                                value: `₹${store.sales_total}`,
                            },
                            {
                                label: 'Distributor',
                                value: `₹${store.distribution_total}`,
                            },
                        ]}
                    />
                </StatGrid>
            </div>
        </>
    );
}

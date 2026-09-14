import { Head } from '@inertiajs/react';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
    dummy_entries: { enabled: boolean; daily_count: number; unassigned: number };
    store: { sales_total: string; sales_count: number; distribution_total: string };
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

            <div className="mx-auto flex max-w-5xl flex-col gap-6 p-4">
                {alerts.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Alerts</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2">
                            {alerts.map((alert, index) => (
                                <p key={index} className="text-destructive text-sm">
                                    {alert}
                                </p>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Members</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm">
                            <p>Total: {members.total}</p>
                            <p>Active: {members.active}</p>
                            <p>Pending: {members.pending}</p>
                            <p>Today: {members.today}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Payment In</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm">
                            <p>Paid: {payments.paid}</p>
                            <p>Pending: {payments.pending}</p>
                            <p>Failed: {payments.failed}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">EMI</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm">
                            <p>Due: {emi.due}</p>
                            <p>Overdue: {emi.overdue}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Income Generated</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm">
                            <p>Level Income: ₹{income.level_income}</p>
                            <p>Pair/Reward: ₹{income.pair_reward}</p>
                            <p>Booster: ₹{income.booster}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Payouts</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm">
                            <p>Pending: {payouts.pending}</p>
                            <p>Processed: {payouts.processed}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Monthly Draw</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm">
                            <p>Active groups: {draw.active_groups}</p>
                            <p>
                                Last winner:{' '}
                                {draw.last_winner_customer_id ?? '—'}
                            </p>
                            <p>
                                Last executed:{' '}
                                {formatDate(draw.last_executed_at)}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Daily Dummy Entries
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm">
                            <p>
                                Status:{' '}
                                {dummy_entries.enabled ? 'Enabled' : 'Disabled'}
                            </p>
                            <p>Daily count: {dummy_entries.daily_count}</p>
                            <p>Unassigned: {dummy_entries.unassigned}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Store Network</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm">
                            <p>Sales: {store.sales_count}</p>
                            <p>Sales Total: ₹{store.sales_total}</p>
                            <p>Distributions: ₹{store.distribution_total}</p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

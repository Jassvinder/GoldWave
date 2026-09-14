import { Head, Link } from '@inertiajs/react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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

            <div className="mx-auto flex max-w-5xl flex-col gap-6 p-4">
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

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Membership Plan</CardTitle>
                            <CardDescription>
                                {plan?.name ?? 'No active plan'}
                            </CardDescription>
                        </CardHeader>
                    </Card>

                    <Link href={emiIndex()}>
                        <Card className="hover:bg-muted/50 h-full transition-colors">
                            <CardHeader>
                                <CardTitle>EMI Schedule</CardTitle>
                                <CardDescription>
                                    {emi
                                        ? `${emi.paid_installments} / ${emi.total_installments} installments paid`
                                        : 'No EMI schedule'}
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    </Link>

                    <Link href={walletIndex()}>
                        <Card className="hover:bg-muted/50 h-full transition-colors">
                            <CardHeader>
                                <CardTitle>Wallet Balance</CardTitle>
                                <CardDescription>
                                    ₹{wallet_balance}
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    </Link>

                    <Link href={showDirects()}>
                        <Card className="hover:bg-muted/50 h-full transition-colors">
                            <CardHeader>
                                <CardTitle>My Team</CardTitle>
                                <CardDescription>
                                    {direct_count} direct members
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    </Link>

                    <Card>
                        <CardHeader>
                            <CardTitle>Income Summary</CardTitle>
                            <CardDescription>
                                Level Income ₹{income.level_income} ·
                                Purchase/Repurchase ₹
                                {income.purchase_repurchase}
                            </CardDescription>
                        </CardHeader>
                    </Card>

                    <Link href={pairRewardIndex()}>
                        <Card className="hover:bg-muted/50 h-full transition-colors">
                            <CardHeader>
                                <CardTitle>Pair/Reward</CardTitle>
                                <CardDescription>
                                    Unused: {pair.unused_left}L /{' '}
                                    {pair.unused_right}R
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    </Link>

                    <Link href={boosterIndex()}>
                        <Card className="hover:bg-muted/50 h-full transition-colors">
                            <CardHeader>
                                <CardTitle>Income Booster</CardTitle>
                                <CardDescription>
                                    {booster_active_levels} active level
                                    {booster_active_levels === 1 ? '' : 's'}
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    </Link>

                    <Link href={drawIndex()}>
                        <Card className="hover:bg-muted/50 h-full transition-colors">
                            <CardHeader>
                                <CardTitle>Monthly Draw</CardTitle>
                                <CardDescription>
                                    {draw_active
                                        ? 'You are in an active draw group'
                                        : 'No active draw group'}
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    </Link>
                </div>
            </div>
        </>
    );
}

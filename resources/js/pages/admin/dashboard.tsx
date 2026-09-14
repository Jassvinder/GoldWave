import { Head } from '@inertiajs/react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Props = {
    store: { name: string; status: string; location: string | null };
    wallet_balance: string;
    sales: { count: number; total: string; repurchase_count: number };
    inventory: { item_count: number; unit_total: number };
    owner_share: string;
    alerts: string[];
};

/** INSTRUCTIONS.md A01 — assigned store sales, repurchases, inventory status, owner share, distributions, alerts. */
export default function AdminDashboard({
    store,
    wallet_balance,
    sales,
    inventory,
    owner_share,
    alerts,
}: Props) {
    return (
        <>
            <Head title="Store Dashboard" />

            <div className="mx-auto flex max-w-5xl flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">{store.name}</h1>
                    <p className="text-muted-foreground text-sm">
                        {store.location ?? 'No location set'} ·{' '}
                        <Badge variant="secondary">{store.status}</Badge>
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
                            <CardTitle>Store Wallet</CardTitle>
                            <CardDescription>
                                ₹{wallet_balance}
                            </CardDescription>
                        </CardHeader>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Sales</CardTitle>
                            <CardDescription>
                                {sales.count} confirmed · ₹{sales.total} total
                                · {sales.repurchase_count} repurchases
                            </CardDescription>
                        </CardHeader>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Inventory</CardTitle>
                            <CardDescription>
                                {inventory.item_count} item types ·{' '}
                                {inventory.unit_total} units in stock
                            </CardDescription>
                        </CardHeader>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Owner Share</CardTitle>
                            <CardDescription>
                                ₹{owner_share} earned to date
                            </CardDescription>
                        </CardHeader>
                    </Card>
                </div>
            </div>
        </>
    );
}

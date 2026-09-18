import { Head } from '@inertiajs/react';
import { Banknote, Boxes, ShoppingCart, WalletCards } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { StatCard, StatGrid } from '@/components/stat-card';

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

            <div className="flex w-full flex-col gap-6 p-4">
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

                <StatGrid>
                    <StatCard
                        icon={WalletCards}
                        color="green"
                        label="Store Wallet"
                        value={`₹${wallet_balance}`}
                    />

                    <StatCard
                        icon={ShoppingCart}
                        color="blue"
                        label="Sales"
                        value={sales.count}
                        stats={[
                            { label: 'Total', value: `₹${sales.total}` },
                            {
                                label: 'Repurchases',
                                value: sales.repurchase_count,
                            },
                        ]}
                    />

                    <StatCard
                        icon={Boxes}
                        color="amber"
                        label="Inventory"
                        value={inventory.item_count}
                        stats={[
                            {
                                label: 'Item types',
                                value: inventory.item_count,
                            },
                            {
                                label: 'Units in stock',
                                value: inventory.unit_total,
                            },
                        ]}
                    />

                    <StatCard
                        icon={Banknote}
                        color="purple"
                        label="Owner Share (Earned To Date)"
                        value={`₹${owner_share}`}
                    />
                </StatGrid>
            </div>
        </>
    );
}

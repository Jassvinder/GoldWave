import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDate } from '@/lib/utils';

type Sale = {
    id: number;
    transaction_type: string;
    customer_id: string | null;
    item_name: string;
    item_weight: string | null;
    rate: string | null;
    total_invoice_amount: string;
    payment_source: string;
    store_wallet_deduction_reference: string | null;
    status: string;
    distribution_status: string;
    invoice_no: string | null;
    created_at: string | null;
};

type ActivityEntry = {
    action_type: string;
    operator_name: string | null;
    affected_customer_id: string | null;
    occurred_at: string | null;
};

type Props = { sales: Sale[]; activity: ActivityEntry[] };

/** INSTRUCTIONS.md A05 — transaction details + operational history (DOMAIN_LOGIC.md §16.3). */
export default function AdminTransactions({ sales, activity }: Props) {
    return (
        <>
            <Head title="Store Transactions" />

            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Transactions
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {sales.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                No transactions yet.
                            </p>
                        )}
                        {sales.map((sale) => (
                            <div
                                key={sale.id}
                                className="rounded-md border p-3 text-sm"
                            >
                                <div className="flex items-center justify-between">
                                    <div className="font-medium capitalize">
                                        {sale.transaction_type.replace(
                                            '_',
                                            ' ',
                                        )}{' '}
                                        — {sale.item_name}
                                    </div>
                                    <Badge variant="secondary">
                                        {sale.status}
                                    </Badge>
                                </div>
                                <div className="text-muted-foreground mt-1">
                                    {sale.customer_id ?? 'Walk-in'} ·{' '}
                                    {sale.item_weight
                                        ? `${sale.item_weight}g · `
                                        : ''}
                                    {sale.rate ? `₹${sale.rate}/g · ` : ''}₹
                                    {sale.total_invoice_amount} ·{' '}
                                    {sale.payment_source}
                                    {sale.store_wallet_deduction_reference
                                        ? ` (ref ${sale.store_wallet_deduction_reference})`
                                        : ''}
                                </div>
                                <div className="text-muted-foreground">
                                    {sale.invoice_no} · Distribution:{' '}
                                    {sale.distribution_status} ·{' '}
                                    {formatDate(sale.created_at)}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Operational History</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {activity.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                No activity recorded yet.
                            </p>
                        )}
                        {activity.map((entry, index) => (
                            <div
                                key={index}
                                className="flex items-center justify-between rounded-md border p-2 text-sm"
                            >
                                <span className="capitalize">
                                    {entry.action_type.replace(/_/g, ' ')}
                                    {entry.affected_customer_id
                                        ? ` — ${entry.affected_customer_id}`
                                        : ''}
                                </span>
                                <span className="text-muted-foreground">
                                    {entry.operator_name} · {formatDate(entry.occurred_at)}
                                </span>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

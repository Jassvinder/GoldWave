import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDate } from '@/lib/utils';

type Entry = {
    id: number;
    entry_type: 'credit' | 'debit';
    category: string;
    amount: string;
    status: 'pending' | 'confirmed' | 'reversed';
    description: string | null;
    processed_at: string | null;
    created_at: string;
};

type Props = {
    wallet_balance: string;
    wallet_hold_amount: string;
    entries: Entry[];
};

/** INSTRUCTIONS.md M14 — balance + full transaction ledger (DOMAIN_LOGIC.md §12). */
export default function Wallet({
    wallet_balance,
    wallet_hold_amount,
    entries,
}: Props) {
    return (
        <>
            <Head title="Wallet" />

            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">Wallet</CardTitle>
                        <CardDescription>
                            Balance ₹{wallet_balance} · On hold ₹
                            {wallet_hold_amount}
                        </CardDescription>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Ledger</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {entries.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                No wallet activity yet.
                            </p>
                        )}
                        {entries.map((entry) => (
                            <div
                                key={entry.id}
                                className="flex items-start justify-between gap-3 rounded-md border p-3"
                            >
                                <div className="min-w-0">
                                    <div className="font-medium capitalize">
                                        {entry.category.replace(/_/g, ' ')}
                                    </div>
                                    <div className="text-muted-foreground text-sm">
                                        {entry.description} ·{' '}
                                        {formatDate(entry.processed_at ?? entry.created_at)}
                                    </div>
                                </div>
                                <div className="flex shrink-0 items-center gap-2 whitespace-nowrap">
                                    <span
                                        className={
                                            entry.entry_type === 'credit'
                                                ? 'text-green-600 dark:text-green-400'
                                                : 'text-destructive'
                                        }
                                    >
                                        {entry.entry_type === 'credit'
                                            ? '+'
                                            : '-'}
                                        ₹{entry.amount}
                                    </span>
                                    <Badge
                                        variant={
                                            entry.status === 'confirmed'
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {entry.status}
                                    </Badge>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

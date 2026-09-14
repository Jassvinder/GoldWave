import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatDate } from '@/lib/utils';
import { store } from '@/routes/member/payout';

type Transaction = {
    method: string;
    reference: string | null;
    net_amount: number;
    status: string;
    processed_at: string | null;
};

type PayoutRequest = {
    id: number;
    requested_amount: string;
    status:
        | 'pending'
        | 'approved'
        | 'processing'
        | 'processed'
        | 'failed'
        | 'cancelled'
        | 'rejected';
    created_at: string;
    transactions: Transaction[];
};

type Props = {
    available_balance: string;
    min_amount: string;
    bank_detail: {
        id: number;
        bank_name: string;
        account_number: string;
        verified_at: string | null;
    } | null;
    requests: PayoutRequest[];
};

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'destructive'> =
    {
        pending: 'secondary',
        approved: 'secondary',
        processing: 'secondary',
        processed: 'default',
        failed: 'destructive',
        cancelled: 'destructive',
        rejected: 'destructive',
    };

/** INSTRUCTIONS.md M15/M16 — request/track withdrawals (DOMAIN_LOGIC.md §11). */
export default function Payout({
    available_balance,
    min_amount,
    bank_detail,
    requests,
}: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const { data, setData, post, processing, errors } = useForm({
        amount: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(store.url());
    };

    return (
        <>
            <Head title="Payout" />

            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Request a Payout
                        </CardTitle>
                        <CardDescription>
                            Available balance ₹{available_balance} · Minimum
                            request ₹{min_amount}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!bank_detail ? (
                            <p className="text-muted-foreground text-sm">
                                Complete your Pending Profile fields to add bank
                                details before requesting a payout.
                            </p>
                        ) : !bank_detail.verified_at ? (
                            <p className="text-muted-foreground text-sm">
                                Your bank details ({bank_detail.bank_name} ···{' '}
                                {bank_detail.account_number.slice(-4)}) are
                                awaiting Super Admin verification.
                            </p>
                        ) : (
                            <form
                                onSubmit={submit}
                                className="flex items-end gap-2"
                            >
                                <div className="grid gap-2">
                                    <Input
                                        type="number"
                                        step="0.01"
                                        placeholder="Amount"
                                        value={data.amount}
                                        onChange={(e) =>
                                            setData('amount', e.target.value)
                                        }
                                    />
                                    {errors.amount && (
                                        <p className="text-destructive text-sm">
                                            {errors.amount}
                                        </p>
                                    )}
                                </div>
                                <Button type="submit" disabled={processing}>
                                    Request Payout
                                </Button>
                            </form>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Payout History</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {requests.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                No payout requests yet.
                            </p>
                        )}
                        {requests.map((request) => (
                            <div
                                key={request.id}
                                className="rounded-md border p-3"
                            >
                                <div className="flex items-center justify-between">
                                    <div>
                                        <div className="font-medium">
                                            ₹{request.requested_amount}
                                        </div>
                                        <div className="text-muted-foreground text-sm">
                                            {formatDate(request.created_at)}
                                        </div>
                                    </div>
                                    <Badge
                                        variant={STATUS_VARIANT[request.status]}
                                    >
                                        {request.status}
                                    </Badge>
                                </div>
                                {request.transactions.map(
                                    (transaction, index) => (
                                        <div
                                            key={index}
                                            className="text-muted-foreground mt-2 border-t pt-2 text-sm"
                                        >
                                            {transaction.method} · Net ₹
                                            {transaction.net_amount}
                                            {transaction.reference
                                                ? ` · Ref ${transaction.reference}`
                                                : ''}
                                            {transaction.processed_at
                                                ? ` · ${formatDate(transaction.processed_at)}`
                                                : ''}
                                        </div>
                                    ),
                                )}
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

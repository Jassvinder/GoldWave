import { Head, useForm, usePage } from '@inertiajs/react';
import { Landmark } from 'lucide-react';
import { FormEventHandler } from 'react';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { FormSection } from '@/components/form-section';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

            <div className="flex w-full flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <FormSection
                    icon={Landmark}
                    color="blue"
                    title="Request a Payout"
                    description={`Available balance ₹${available_balance} · Minimum request ₹${min_amount}`}
                >
                    {!bank_detail ? (
                        <p className="text-muted-foreground text-sm">
                            Complete your Pending Profile fields to add bank
                            details before requesting a payout.
                        </p>
                    ) : !bank_detail.verified_at ? (
                        <p className="text-muted-foreground text-sm">
                            Your bank details ({bank_detail.bank_name} ···{' '}
                            {bank_detail.account_number.slice(-4)}) are awaiting
                            Super Admin verification.
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
                </FormSection>

                <Card>
                    <CardHeader>
                        <CardTitle>Payout History</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            columns={requestColumns}
                            rows={requests}
                            rowKey={(row) => row.id}
                            emptyMessage="No payout requests yet."
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

const requestColumns: DataTableColumn<PayoutRequest>[] = [
    {
        key: 'requested_amount',
        header: 'Amount',
        render: (row) => (
            <span className="font-medium">₹{row.requested_amount}</span>
        ),
    },
    {
        key: 'created_at',
        header: 'Requested',
        render: (row) => formatDate(row.created_at),
    },
    {
        key: 'transactions',
        header: 'Transaction',
        render: (row) =>
            row.transactions.length === 0
                ? '—'
                : row.transactions.map((transaction, index) => (
                      <div
                          key={index}
                          className="text-muted-foreground text-xs"
                      >
                          {transaction.method} · Net ₹{transaction.net_amount}
                          {transaction.reference
                              ? ` · Ref ${transaction.reference}`
                              : ''}
                          {transaction.processed_at
                              ? ` · ${formatDate(transaction.processed_at)}`
                              : ''}
                      </div>
                  )),
    },
    {
        key: 'status',
        header: 'Status',
        render: (row) => (
            <Badge variant={STATUS_VARIANT[row.status]}>{row.status}</Badge>
        ),
    },
];

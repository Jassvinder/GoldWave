import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/lib/utils';

type Payment = {
    id: number;
    type: 'registration' | 'emi_installment';
    installment_no: number | null;
    amount: string;
    mode: 'online' | 'cash';
    status: 'pending' | 'paid' | 'failed';
    provider_reference: string | null;
    paid_at: string | null;
};

type Props = { payments: Payment[] };

const STATUS_VARIANT: Record<
    Payment['status'],
    'default' | 'secondary' | 'destructive'
> = {
    pending: 'secondary',
    paid: 'default',
    failed: 'destructive',
};

/** INSTRUCTIONS.md M07 — every Payment In transaction (registration + EMI installments). */
export default function PaymentHistory({ payments }: Props) {
    return (
        <>
            <Head title="Payment History" />

            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Payment History
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {payments.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                No payments yet.
                            </p>
                        )}
                        {payments.map((payment) => (
                            <div
                                key={payment.id}
                                className="flex items-center justify-between rounded-md border p-3"
                            >
                                <div>
                                    <div className="font-medium">
                                        {payment.type === 'registration'
                                            ? 'Registration'
                                            : `Installment #${payment.installment_no}`}
                                    </div>
                                    <div className="text-muted-foreground text-sm">
                                        ₹{payment.amount} · {payment.mode}
                                        {payment.provider_reference
                                            ? ` · Ref ${payment.provider_reference}`
                                            : ''}
                                        {payment.paid_at
                                            ? ` · ${formatDate(payment.paid_at)}`
                                            : ''}
                                    </div>
                                </div>
                                <Badge variant={STATUS_VARIANT[payment.status]}>
                                    {payment.status}
                                </Badge>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

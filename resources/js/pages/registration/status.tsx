import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Props = {
    member: {
        id: number;
        customer_id: string | null;
        status: string;
        plan: string | null;
    };
    payment: {
        amount: string;
        mode: 'online' | 'cash';
        status: string;
        cash_status: string | null;
    } | null;
};

const statusLabel: Record<string, string> = {
    draft: 'Draft',
    payment_pending: 'Payment Pending',
    payment_confirmed: 'Payment Confirmed',
    active: 'Active',
    cancelled: 'Cancelled',
};

export default function RegistrationStatus({ member, payment }: Props) {
    return (
        <>
            <Head title="Registration Status" />

            <div className="mx-auto flex min-h-screen max-w-lg flex-col justify-center gap-6 px-4 py-10">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Registration Status
                        </CardTitle>
                        <CardDescription>
                            {member.plan ?? 'GoldWave Membership'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div className="flex items-center justify-between">
                            <span className="text-muted-foreground text-sm">
                                Status
                            </span>
                            <Badge
                                variant={
                                    member.status === 'active'
                                        ? 'default'
                                        : 'secondary'
                                }
                            >
                                {statusLabel[member.status] ?? member.status}
                            </Badge>
                        </div>

                        {member.customer_id && (
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground text-sm">
                                    Customer ID
                                </span>
                                <span className="font-mono font-medium">
                                    {member.customer_id}
                                </span>
                            </div>
                        )}

                        {payment && (
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground text-sm">
                                    Payment
                                </span>
                                <span className="text-sm">
                                    ₹{payment.amount} · {payment.mode} ·{' '}
                                    {payment.cash_status ?? payment.status}
                                </span>
                            </div>
                        )}

                        {member.status === 'payment_pending' &&
                            payment?.mode === 'cash' && (
                                <p className="text-muted-foreground text-sm">
                                    Your cash payment is awaiting confirmation
                                    by GoldWave staff. You will be able to log
                                    in once your membership is activated.
                                </p>
                            )}

                        {member.status === 'active' && (
                            <p className="text-sm text-green-600">
                                Your membership is active. Use your Customer ID
                                to log in — your initial password is your
                                Customer ID itself.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

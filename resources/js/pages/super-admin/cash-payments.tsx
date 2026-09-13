import { Head, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import * as cashPayments from '@/routes/super-admin/cash-payments';

type PendingPayment = {
    id: number;
    type: 'registration' | 'emi_installment';
    amount: string;
    created_at: string;
    member: {
        customer_id: string | null;
        user: { name: string; email: string } | null;
    } | null;
    emi_installment: { installment_no: number } | null;
};

type Props = {
    pending: PendingPayment[];
};

/**
 * DOMAIN_LOGIC.md §3.1 step 8 / §10.2 — Super Admin confirms a member's cash
 * registration payment before their membership can activate.
 */
export default function CashPayments({ pending }: Props) {
    function approve(id: number) {
        router.post(cashPayments.approve.url(id), {}, { preserveScroll: true });
    }

    function reject(id: number) {
        router.post(cashPayments.reject.url(id), {}, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Cash Payments" />

            <div className="mx-auto flex max-w-3xl flex-col gap-6 px-4 py-10">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Pending Cash Payments
                        </CardTitle>
                        <CardDescription>
                            Confirm receipt to activate the member&apos;s
                            registration.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {pending.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                No cash payments awaiting confirmation.
                            </p>
                        )}

                        {pending.map((payment) => (
                            <div
                                key={payment.id}
                                className="flex items-center justify-between rounded-md border p-3"
                            >
                                <div>
                                    <div className="font-medium">
                                        {payment.member?.user?.name ??
                                            'Unknown member'}
                                    </div>
                                    <div className="text-muted-foreground text-sm">
                                        {payment.member?.user?.email} · ₹
                                        {payment.amount}
                                        {payment.type === 'emi_installment' &&
                                            payment.emi_installment &&
                                            ` · EMI installment #${payment.emi_installment.installment_no}`}
                                    </div>
                                    <Badge variant="secondary" className="mt-1">
                                        {payment.type === 'registration'
                                            ? 'Registration — Pending Verification'
                                            : 'EMI Installment — Pending Verification'}
                                    </Badge>
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => reject(payment.id)}
                                    >
                                        Reject
                                    </Button>
                                    <Button
                                        size="sm"
                                        onClick={() => approve(payment.id)}
                                    >
                                        Approve
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

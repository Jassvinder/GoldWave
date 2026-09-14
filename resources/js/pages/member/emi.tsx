import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDate } from '@/lib/utils';
import { pay as payInstallment } from '@/routes/member/emi';

type Installment = {
    id: number;
    installment_no: number;
    due_date: string;
    amount: string;
    status: 'upcoming' | 'due' | 'paid' | 'failed' | 'overdue';
    paid_at: string | null;
    payment_reference: string | null;
    payment_mode: 'online' | 'cash' | null;
};

type PairEligibility = {
    required_emis: number;
    completed_emis: number;
    eligible: boolean;
};

type Props = {
    schedule: {
        rate_booking_method: string;
        installment_amount: string;
        total_installments: number;
    } | null;
    installments: Installment[];
    pair_eligibility: PairEligibility | null;
};

const STATUS_VARIANT: Record<
    Installment['status'],
    'default' | 'secondary' | 'destructive'
> = {
    upcoming: 'secondary',
    due: 'default',
    paid: 'default',
    failed: 'destructive',
    overdue: 'destructive',
};

/**
 * INSTRUCTIONS.md M06 — full EMI Schedule: installment list, paid
 * date/reference/mode, Pay action, and the Pair/Reward eligibility indicator
 * (DOMAIN_LOGIC.md §5/§7.3). Only the earliest unpaid (due/overdue)
 * installment is payable (§5 item 8) — the Pay action only ever appears next
 * to that one row.
 */
export default function Emi({
    schedule,
    installments,
    pair_eligibility,
}: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const [mode, setMode] = useState<'online' | 'cash'>('online');

    const nextPayable = installments.find(
        (installment) =>
            installment.status === 'due' || installment.status === 'overdue',
    );

    function pay(installmentId: number) {
        router.post(payInstallment.url(installmentId), { mode });
    }

    return (
        <>
            <Head title="EMI Schedule" />

            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">EMI Schedule</CardTitle>
                        <CardDescription>
                            {schedule
                                ? `${schedule.rate_booking_method === 'current_rate' ? 'Current Rate Booking' : 'Future Rate Booking'} — ₹${schedule.installment_amount}/month × ${schedule.total_installments}`
                                : 'No EMI schedule on this membership.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {pair_eligibility && (
                            <div className="mb-2 rounded-md border p-3 text-sm">
                                <span className="font-medium">
                                    Pair/Reward eligibility:
                                </span>{' '}
                                {pair_eligibility.eligible ? (
                                    <Badge>Eligible</Badge>
                                ) : (
                                    <Badge variant="secondary">
                                        {pair_eligibility.completed_emis} /{' '}
                                        {pair_eligibility.required_emis}{' '}
                                        installments needed
                                    </Badge>
                                )}
                            </div>
                        )}

                        {installments.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                No installments to show.
                            </p>
                        )}

                        {installments.map((installment) => {
                            const isPayable =
                                nextPayable?.id === installment.id;

                            return (
                                <div
                                    key={installment.id}
                                    className="flex items-center justify-between rounded-md border p-3"
                                >
                                    <div>
                                        <div className="font-medium">
                                            Installment #
                                            {installment.installment_no}
                                        </div>
                                        <div className="text-muted-foreground text-sm">
                                            Due {formatDate(installment.due_date)} · ₹
                                            {installment.amount}
                                        </div>
                                        {installment.paid_at && (
                                            <div className="text-muted-foreground text-sm">
                                                Paid {formatDate(installment.paid_at)} via{' '}
                                                {installment.payment_mode}
                                                {installment.payment_reference
                                                    ? ` · Ref ${installment.payment_reference}`
                                                    : ''}
                                            </div>
                                        )}
                                        <Badge
                                            variant={
                                                STATUS_VARIANT[
                                                    installment.status
                                                ]
                                            }
                                            className="mt-1"
                                        >
                                            {installment.status}
                                        </Badge>
                                    </div>

                                    {isPayable && (
                                        <div className="flex items-center gap-2">
                                            <select
                                                className="border-input bg-background rounded-md border px-2 py-1 text-sm"
                                                value={mode}
                                                onChange={(event) =>
                                                    setMode(
                                                        event.target.value as
                                                            | 'online'
                                                            | 'cash',
                                                    )
                                                }
                                            >
                                                <option value="online">
                                                    Online
                                                </option>
                                                <option value="cash">
                                                    Cash
                                                </option>
                                            </select>
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    pay(installment.id)
                                                }
                                            >
                                                Pay
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

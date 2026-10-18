import { Head, usePoll } from '@inertiajs/react';
import { CheckCircle2, PartyPopper, XCircle } from 'lucide-react';
import { useEffect } from 'react';
import { PublicLogoLink } from '@/components/public-logo-link';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { login as memberLogin } from '@/routes/member';

type Props = {
    member: {
        id: number;
        customer_id: string | null;
        name: string | null;
        status: string;
        plan: string | null;
    };
    payment: {
        amount: string;
        mode: 'online' | 'cash' | 'upi' | 'wallet';
        status: string;
        cash_status: string | null;
    } | null;
    /** T-229 — the payment is being confirmed in the background right now. */
    processing: boolean;
    /** Signed checkout link while an online payment is still pending (T-137). */
    pay_url: string | null;
    payment_error: string | null;
};

const statusLabel: Record<string, string> = {
    draft: 'Draft',
    payment_pending: 'Payment Pending',
    payment_confirmed: 'Payment Confirmed',
    active: 'Active',
    cancelled: 'Cancelled',
};

/** T-219 — readable payment mode and state instead of the raw codes (e.g. "cash · pending_verification"). */
const modeLabel: Record<'online' | 'cash' | 'upi' | 'wallet', string> = {
    cash: 'Cash',
    upi: 'GPay/UPI',
    online: 'Online',
    wallet: 'Wallet',
};

const paymentStateLabel: Record<string, string> = {
    pending_verification: 'Awaiting approval',
    approved: 'Approved',
    rejected: 'Rejected',
    pending: 'Pending',
    paid: 'Paid',
    completed: 'Paid',
    failed: 'Failed',
    cancelled: 'Cancelled',
};

export default function RegistrationStatus({
    member,
    payment,
    processing,
    pay_url,
    payment_error,
}: Props) {
    const isActive = member.status === 'active';
    const isCancelled = member.status === 'cancelled';

    // T-229 — refresh every few seconds while the payment is confirmed in the background.
    const { start, stop } = usePoll(3000, {}, { autoStart: false });

    useEffect(() => {
        if (processing) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [processing, start, stop]);

    return (
        <>
            <Head title="Registration Status" />

            <div className="mx-auto flex min-h-screen max-w-lg flex-col justify-center gap-6 px-4 py-10">
                <PublicLogoLink />

                <Card className="gap-0 overflow-hidden pt-0">
                    {/* T-236 — welcome header with the member's name */}
                    <CardHeader className="bg-linear-to-br from-amber-500 via-yellow-500 to-amber-600 px-6 py-8 text-center text-white">
                        <div className="mx-auto mb-3 flex size-14 items-center justify-center rounded-full bg-white/20 ring-4 ring-white/30">
                            {isActive ? (
                                <PartyPopper className="size-7" aria-hidden />
                            ) : isCancelled ? (
                                <XCircle className="size-7" aria-hidden />
                            ) : (
                                <CheckCircle2 className="size-7" aria-hidden />
                            )}
                        </div>
                        <CardTitle className="font-display text-2xl leading-tight break-words sm:text-3xl">
                            {isCancelled ? 'Hello' : 'Welcome to GoldWave'}
                            {member.name ? `, ${member.name}` : ''}!
                        </CardTitle>
                        <CardDescription className="mx-auto mt-2 max-w-sm text-white/90">
                            {isActive
                                ? "Your membership is active — we're glad to have you in the GoldWave family."
                                : isCancelled
                                  ? 'This registration was cancelled. Please contact GoldWave for help.'
                                  : 'Thank you for joining us. Your registration has been received.'}
                        </CardDescription>
                        {member.plan && (
                            <span className="mx-auto mt-4 inline-block rounded-full bg-white/20 px-3 py-1 text-sm font-medium">
                                {member.plan}
                            </span>
                        )}
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4 pt-6">
                        <div className="flex items-center justify-between">
                            <span className="text-muted-foreground text-sm">
                                Status
                            </span>
                            <Badge
                                variant={
                                    member.status === 'active'
                                        ? 'success'
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
                                <span className="text-right text-sm">
                                    ₹
                                    {Number(payment.amount).toLocaleString(
                                        'en-IN',
                                    )}{' '}
                                    · {modeLabel[payment.mode]} ·{' '}
                                    {paymentStateLabel[
                                        payment.cash_status ?? payment.status
                                    ] ??
                                        payment.cash_status ??
                                        payment.status}
                                </span>
                            </div>
                        )}

                        {payment_error && (
                            <p
                                role="alert"
                                className="border-destructive/40 bg-destructive/10 text-destructive rounded-md border p-3 text-sm"
                            >
                                {payment_error}
                            </p>
                        )}

                        {processing && (
                            <p className="flex items-center gap-2 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200">
                                <Spinner className="shrink-0" />
                                Payment received — confirming the membership.
                                This takes a few seconds; this page updates by
                                itself.
                            </p>
                        )}

                        {!processing &&
                            payment?.mode === 'wallet' &&
                            payment.status === 'pending' && (
                                <p
                                    role="alert"
                                    className="border-destructive/40 bg-destructive/10 text-destructive rounded-md border p-3 text-sm"
                                >
                                    The wallet payment could not be completed.
                                    No wallet was charged. Please contact
                                    GoldWave to complete this registration.
                                </p>
                            )}

                        {pay_url && (
                            <div className="flex flex-col gap-2">
                                <p className="text-muted-foreground text-sm">
                                    Your registration is saved but the payment
                                    is not complete yet.
                                </p>
                                <Button asChild>
                                    <a href={pay_url}>Pay now</a>
                                </Button>
                            </div>
                        )}

                        {member.status === 'payment_pending' &&
                            (payment?.mode === 'cash' ||
                                payment?.mode === 'upi') && (
                                <p className="text-muted-foreground text-sm">
                                    Your{' '}
                                    {payment.mode === 'upi'
                                        ? 'GPay/UPI'
                                        : 'cash'}{' '}
                                    payment is awaiting confirmation by GoldWave
                                    staff. You will be able to log in once your
                                    membership is activated.
                                </p>
                            )}

                        {isActive && (
                            <div className="flex flex-col gap-3 rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-green-950">
                                <p className="text-sm text-green-800 dark:text-green-200">
                                    Use your Customer ID to log in — your
                                    initial password is your Customer ID itself.
                                </p>
                                <Button asChild className="w-full sm:w-auto">
                                    <a href={memberLogin.url()}>
                                        Go to Member Login
                                    </a>
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

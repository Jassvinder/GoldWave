import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { PublicLogoLink } from '@/components/public-logo-link';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';

type CheckoutSuccess = {
    razorpay_payment_id: string;
    razorpay_order_id: string;
    razorpay_signature: string;
};

type RazorpayInstance = {
    open: () => void;
    on: (
        event: 'payment.failed',
        callback: (response: { error?: { description?: string } }) => void,
    ) => void;
};

declare global {
    interface Window {
        Razorpay?: new (options: Record<string, unknown>) => RazorpayInstance;
    }
}

type Props = {
    payment: { id: number; amount: string; type: string };
    razorpay: {
        key: string;
        order_id: string;
        amount: number;
        currency: string;
        name: string;
    } | null;
    error: string | null;
    prefill: {
        name: string | null;
        email: string | null;
        contact: string | null;
    };
    verifyUrl: string;
    statusUrl: string;
};

const CHECKOUT_SCRIPT = 'https://checkout.razorpay.com/v1/checkout.js';

function loadCheckoutScript(): Promise<boolean> {
    if (window.Razorpay) {
        return Promise.resolve(true);
    }

    return new Promise((resolve) => {
        const script = document.createElement('script');
        script.src = CHECKOUT_SCRIPT;
        script.onload = () => resolve(true);
        script.onerror = () => resolve(false);
        document.body.appendChild(script);
    });
}

/**
 * T-137 — our page around Razorpay Checkout (DOMAIN_LOGIC.md §10.1). Checkout's success callback is only handed to the
 * server (`verifyUrl`), which verifies it with Razorpay before anything is activated — this page never marks a payment
 * as paid itself. Reached only through a signed, expiring URL.
 */
export default function RazorpayCheckout({
    payment,
    razorpay,
    error,
    prefill,
    verifyUrl,
    statusUrl,
}: Props) {
    const serverErrors = usePage().props.errors as Record<string, string>;
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState<string | null>(error);
    const opened = useRef(false);

    const serverMessage = serverErrors?.payment;

    async function pay() {
        if (!razorpay) {
            return;
        }

        setMessage(null);
        setBusy(true);

        const loaded = await loadCheckoutScript();

        if (!loaded || !window.Razorpay) {
            setBusy(false);
            setMessage(
                'Could not load the payment window. Check your internet connection and try again.',
            );

            return;
        }

        const checkout = new window.Razorpay({
            key: razorpay.key,
            amount: razorpay.amount,
            currency: razorpay.currency,
            name: razorpay.name,
            description:
                payment.type === 'registration'
                    ? 'Membership registration'
                    : 'EMI instalment',
            order_id: razorpay.order_id,
            prefill: {
                name: prefill.name ?? undefined,
                email: prefill.email ?? undefined,
                contact: prefill.contact ?? undefined,
            },
            theme: { color: '#d97706' },
            handler: (response: CheckoutSuccess) => {
                setBusy(true);
                router.post(verifyUrl, response, {
                    onError: () => setBusy(false),
                    onFinish: () => setBusy(false),
                });
            },
            modal: {
                ondismiss: () => setBusy(false),
            },
        });

        checkout.on('payment.failed', (response) => {
            setBusy(false);
            setMessage(
                response.error?.description ??
                    'The payment failed. You can try again.',
            );
        });

        checkout.open();
    }

    // Open Checkout straight away once, so the member does not need a second click after being redirected here.
    useEffect(() => {
        if (razorpay && !opened.current) {
            opened.current = true;
            void pay();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const shownMessage = serverMessage ?? message;

    return (
        <>
            <Head title="Pay securely" />

            <div className="mx-auto flex min-h-screen max-w-md flex-col justify-center gap-6 px-4 py-10">
                <PublicLogoLink />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Pay ₹{payment.amount}
                        </CardTitle>
                        <CardDescription>
                            {payment.type === 'registration'
                                ? 'Membership registration'
                                : 'EMI instalment'}{' '}
                            — secure payment by Razorpay (UPI, cards,
                            netbanking, wallets).
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        {shownMessage && (
                            <p
                                role="alert"
                                className="border-destructive/40 bg-destructive/10 text-destructive rounded-md border p-3 text-sm"
                            >
                                {shownMessage}
                            </p>
                        )}

                        {razorpay ? (
                            <Button onClick={() => void pay()} disabled={busy}>
                                {busy && <Spinner />}
                                {busy ? 'Please wait…' : 'Pay now'}
                            </Button>
                        ) : (
                            <Button
                                variant="outline"
                                onClick={() => router.reload()}
                            >
                                Try again
                            </Button>
                        )}

                        <a
                            href={statusUrl}
                            className="text-muted-foreground text-center text-sm underline"
                        >
                            Not now — go back
                        </a>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    payment: { id: number; amount: string; mode: string };
    webhookUrl: string;
    statusUrl: string;
    fields: { payment_id: number; reference: string; signature: string };
};

/**
 * Dev/local-only stand-in for a real gateway's hosted checkout page — see
 * FakePaymentGateway (ARCHITECTURE.md: no real payment gateway vendor chosen
 * yet). Posts a correctly-signed callback straight to the real webhook
 * endpoint, exercising the exact verification path a genuine gateway would.
 */
export default function PaymentDevSimulate({
    payment,
    webhookUrl,
    statusUrl,
    fields,
}: Props) {
    const [state, setState] = useState<
        'idle' | 'confirming' | 'done' | 'error'
    >('idle');

    async function simulate() {
        setState('confirming');

        const response = await fetch(webhookUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: JSON.stringify(fields),
        });

        if (response.ok) {
            setState('done');
            window.location.href = statusUrl;
        } else {
            setState('error');
        }
    }

    return (
        <>
            <Head title="Simulated Payment (Dev)" />

            <div className="mx-auto flex min-h-screen max-w-md flex-col justify-center gap-6 px-4 py-10">
                <Card>
                    <CardHeader>
                        <CardTitle>Simulated Payment Gateway</CardTitle>
                        <CardDescription>
                            Dev-only stand-in — no real gateway is connected
                            yet. Amount ₹{payment.amount}.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <Button
                            onClick={simulate}
                            disabled={
                                state === 'confirming' || state === 'done'
                            }
                        >
                            {state === 'confirming' && <Spinner />}
                            Simulate Successful Payment
                        </Button>
                        {state === 'error' && (
                            <p className="text-destructive text-sm">
                                Simulation failed — please try again.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

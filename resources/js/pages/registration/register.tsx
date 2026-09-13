import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store, validateSponsor } from '@/routes/registration';

type Plan = {
    id: number;
    code: string;
    name: string;
    amount: string;
    installment_count: number | null;
    product_category: 'gold' | 'silver';
    fixed_weight_grams: string | null;
};

type Props = {
    plans: Plan[];
};

type SponsorState =
    | { status: 'idle' }
    | { status: 'checking' }
    | { status: 'valid'; name: string | null; customerId: string }
    | { status: 'invalid'; message: string };

export default function Register({ plans }: Props) {
    const [sponsor, setSponsor] = useState<SponsorState>({ status: 'idle' });

    const form = useForm({
        sponsor_code: '',
        placement_side: 'left' as 'left' | 'right',
        name: '',
        email: '',
        mobile: '',
        membership_plan_id: '' as number | '',
        rate_booking_method: '' as 'current_rate' | 'future_rate' | '',
        payment_mode: 'online' as 'online' | 'cash',
    });

    const selectedPlan =
        plans.find((plan) => plan.id === form.data.membership_plan_id) ?? null;
    const isEmiPlan =
        selectedPlan !== null && selectedPlan.installment_count !== null;

    async function checkSponsorCode(code: string) {
        if (!code) {
            setSponsor({ status: 'idle' });
            return;
        }

        setSponsor({ status: 'checking' });

        const xsrfToken = decodeURIComponent(
            document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
        );

        const response = await fetch(validateSponsor.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken,
            },
            body: JSON.stringify({ sponsor_code: code }),
        });

        const body = await response.json();

        if (response.ok && body.valid) {
            setSponsor({
                status: 'valid',
                name: body.sponsor_name,
                customerId: body.sponsor_customer_id,
            });
        } else {
            setSponsor({
                status: 'invalid',
                message: body.message ?? 'Invalid sponsor code.',
            });
        }
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();
        form.post(store.url());
    }

    return (
        <>
            <Head title="Join GoldWave" />

            <div className="mx-auto flex min-h-screen max-w-2xl flex-col justify-center gap-6 px-4 py-10">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Join GoldWave
                        </CardTitle>
                        <CardDescription>
                            Enter your sponsor&apos;s code to begin
                            registration.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="flex flex-col gap-6">
                            {/* Sponsor code */}
                            <div className="grid gap-2">
                                <Label htmlFor="sponsor_code">
                                    Invitation / Sponsor Code
                                </Label>
                                <Input
                                    id="sponsor_code"
                                    value={form.data.sponsor_code}
                                    onChange={(e) =>
                                        form.setData(
                                            'sponsor_code',
                                            e.target.value,
                                        )
                                    }
                                    onBlur={(e) =>
                                        checkSponsorCode(e.target.value)
                                    }
                                    placeholder="GWL01"
                                    required
                                />
                                {sponsor.status === 'checking' && (
                                    <p className="text-muted-foreground text-sm">
                                        Checking…
                                    </p>
                                )}
                                {sponsor.status === 'valid' && (
                                    <p className="text-sm text-green-600">
                                        Sponsor:{' '}
                                        {sponsor.name ?? sponsor.customerId} (
                                        {sponsor.customerId})
                                    </p>
                                )}
                                {sponsor.status === 'invalid' && (
                                    <p className="text-destructive text-sm">
                                        {sponsor.message}
                                    </p>
                                )}
                                <InputError
                                    message={form.errors.sponsor_code}
                                />
                            </div>

                            {/* Placement side */}
                            <div className="grid gap-2">
                                <Label>Placement Side</Label>
                                <div className="flex gap-2">
                                    {(['left', 'right'] as const).map(
                                        (side) => (
                                            <button
                                                key={side}
                                                type="button"
                                                onClick={() =>
                                                    form.setData(
                                                        'placement_side',
                                                        side,
                                                    )
                                                }
                                                className={`flex-1 rounded-md border px-3 py-2 text-sm capitalize ${
                                                    form.data.placement_side ===
                                                    side
                                                        ? 'border-primary bg-primary text-primary-foreground'
                                                        : 'border-input bg-transparent'
                                                }`}
                                            >
                                                {side}
                                            </button>
                                        ),
                                    )}
                                </div>
                                <InputError
                                    message={form.errors.placement_side}
                                />
                            </div>

                            {/* Member details */}
                            <div className="grid gap-2">
                                <Label htmlFor="name">Full Name</Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    required
                                />
                                <InputError message={form.errors.name} />
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={form.data.email}
                                        onChange={(e) =>
                                            form.setData(
                                                'email',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError message={form.errors.email} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="mobile">
                                        Mobile Number
                                    </Label>
                                    <Input
                                        id="mobile"
                                        value={form.data.mobile}
                                        onChange={(e) =>
                                            form.setData(
                                                'mobile',
                                                e.target.value.replace(
                                                    /\D/g,
                                                    '',
                                                ),
                                            )
                                        }
                                        maxLength={10}
                                        placeholder="9876543210"
                                        required
                                    />
                                    <InputError message={form.errors.mobile} />
                                </div>
                            </div>

                            {/* Plan selection */}
                            <div className="grid gap-2">
                                <Label>Membership Plan</Label>
                                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    {plans.map((plan) => (
                                        <button
                                            key={plan.id}
                                            type="button"
                                            onClick={() => {
                                                form.setData(
                                                    'membership_plan_id',
                                                    plan.id,
                                                );
                                                form.setData(
                                                    'rate_booking_method',
                                                    '',
                                                );
                                            }}
                                            className={`rounded-md border p-3 text-left text-sm ${
                                                form.data.membership_plan_id ===
                                                plan.id
                                                    ? 'border-primary bg-primary/5'
                                                    : 'border-input'
                                            }`}
                                        >
                                            <div className="font-medium">
                                                {plan.name}
                                            </div>
                                            <div className="text-muted-foreground">
                                                {plan.installment_count
                                                    ? `₹${plan.amount}/month × ${plan.installment_count}`
                                                    : `₹${plan.amount} one-time`}
                                                {' — '}
                                                {plan.fixed_weight_grams
                                                    ? `${plan.fixed_weight_grams}g `
                                                    : ''}
                                                {plan.product_category}
                                            </div>
                                        </button>
                                    ))}
                                </div>
                                <InputError
                                    message={form.errors.membership_plan_id}
                                />
                            </div>

                            {/* Rate booking method — EMI plans only */}
                            {isEmiPlan && (
                                <div className="grid gap-2">
                                    <Label>Rate Booking</Label>
                                    <div className="flex gap-2">
                                        {(
                                            [
                                                {
                                                    value: 'current_rate',
                                                    label: 'Current Rate Booking',
                                                },
                                                {
                                                    value: 'future_rate',
                                                    label: 'Future Rate Booking',
                                                },
                                            ] as const
                                        ).map((option) => (
                                            <button
                                                key={option.value}
                                                type="button"
                                                onClick={() =>
                                                    form.setData(
                                                        'rate_booking_method',
                                                        option.value,
                                                    )
                                                }
                                                className={`flex-1 rounded-md border px-3 py-2 text-sm ${
                                                    form.data
                                                        .rate_booking_method ===
                                                    option.value
                                                        ? 'border-primary bg-primary text-primary-foreground'
                                                        : 'border-input bg-transparent'
                                                }`}
                                            >
                                                {option.label}
                                            </button>
                                        ))}
                                    </div>
                                    <InputError
                                        message={
                                            form.errors.rate_booking_method
                                        }
                                    />
                                </div>
                            )}

                            {/* Payment mode */}
                            <div className="grid gap-2">
                                <Label>Payment Mode</Label>
                                <div className="flex gap-2">
                                    {(
                                        [
                                            {
                                                value: 'online',
                                                label: 'Online',
                                            },
                                            { value: 'cash', label: 'Cash' },
                                        ] as const
                                    ).map((option) => (
                                        <button
                                            key={option.value}
                                            type="button"
                                            onClick={() =>
                                                form.setData(
                                                    'payment_mode',
                                                    option.value,
                                                )
                                            }
                                            className={`flex-1 rounded-md border px-3 py-2 text-sm ${
                                                form.data.payment_mode ===
                                                option.value
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-input bg-transparent'
                                            }`}
                                        >
                                            {option.label}
                                        </button>
                                    ))}
                                </div>
                                <InputError
                                    message={form.errors.payment_mode}
                                />
                            </div>

                            <Button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    sponsor.status !== 'valid'
                                }
                            >
                                {form.processing && <Spinner />}
                                Continue to Payment
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

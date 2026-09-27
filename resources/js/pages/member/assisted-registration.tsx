import { Head, usePage, useForm } from '@inertiajs/react';
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
import { store } from '@/routes/member/assisted-registration';
import { validateSponsor } from '@/routes/registration';

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
    wallet_balance: number;
};

type SponsorState =
    | { status: 'idle' }
    | { status: 'checking' }
    | { status: 'valid'; name: string | null; customerId: string }
    | { status: 'invalid'; message: string };

/** DOMAIN_LOGIC.md §12.2(b) — T-153. Register a different, new member from your own portal; pay from your own wallet, optionally. */
export default function MemberAssistedRegistration({
    plans,
    wallet_balance,
}: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const [sponsor, setSponsor] = useState<SponsorState>({ status: 'idle' });

    const form = useForm({
        sponsor_code: '',
        placement_side: 'left' as 'left' | 'right',
        name: '',
        gender: '' as 'male' | 'female' | 'other' | '',
        email: '',
        mobile: '',
        membership_plan_id: '' as number | '',
        payment_mode: 'online' as 'online' | 'cash' | 'wallet',
    });

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
            <Head title="Register a New Member" />

            <div className="flex w-full flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Register a New Member
                        </CardTitle>
                        <CardDescription>
                            This registration is for someone else joining
                            GoldWave — the sponsor still needs their own
                            sponsor code, same as the public join form. Your
                            wallet balance is ₹{wallet_balance.toFixed(2)} —
                            you may optionally pay their registration from it.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="flex flex-col gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="sponsor_code">
                                    New Member&apos;s Sponsor Code
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

                            <div className="grid gap-2">
                                <Label htmlFor="name">
                                    New Member&apos;s Full Name
                                </Label>
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

                            <div className="grid gap-2">
                                <Label htmlFor="gender">Gender</Label>
                                <select
                                    id="gender"
                                    className="border-input bg-background rounded-md border px-3 py-2 text-sm"
                                    value={form.data.gender}
                                    onChange={(e) =>
                                        form.setData(
                                            'gender',
                                            e.target.value as
                                                | 'male'
                                                | 'female'
                                                | 'other'
                                                | '',
                                        )
                                    }
                                    required
                                >
                                    <option value="">Select gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                                <InputError message={form.errors.gender} />
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

                            <div className="grid gap-2">
                                <Label>Payment Mode</Label>
                                <div className="flex gap-2">
                                    {(
                                        [
                                            {
                                                value: 'online',
                                                label: 'Online (new member pays)',
                                            },
                                            {
                                                value: 'cash',
                                                label: 'Cash (new member pays)',
                                            },
                                            {
                                                value: 'wallet',
                                                label: 'My Wallet',
                                            },
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
                                {form.data.payment_mode === 'wallet' && (
                                    <p className="text-muted-foreground text-sm">
                                        The registration amount will be
                                        deducted from your own wallet balance
                                        (₹{wallet_balance.toFixed(2)}{' '}
                                        available) and the new member is
                                        activated immediately.
                                    </p>
                                )}
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
                                Register
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { password as loginWithPassword } from '@/routes/member/login';
import * as otp from '@/routes/member/login/otp';
import { reset as passwordResetRoute } from '@/routes/member/password';

type Props = {
    status?: string;
};

/**
 * DOMAIN_LOGIC.md §2.2 — dual login methods, both always available side by
 * side: OTP (mobile-or-email) and Customer ID + password. Rendered inside
 * AuthLayout (name starts with `auth/`, see app.tsx) — do not add an outer
 * centered/Card wrapper here, AuthLayout already provides one.
 */
export default function GoldWaveLogin({ status }: Props) {
    const [method, setMethod] = useState<'otp' | 'password'>('password');
    const [otpRequested, setOtpRequested] = useState(false);

    const otpForm = useForm({ identifier: '', otp: '' });
    const passwordForm = useForm({ customer_id: '', password: '' });

    function requestOtp(event: React.FormEvent) {
        event.preventDefault();
        otpForm.post(otp.request.url(), {
            preserveScroll: true,
            onSuccess: () => setOtpRequested(true),
        });
    }

    function verifyOtp(event: React.FormEvent) {
        event.preventDefault();
        otpForm.post(otp.verify.url());
    }

    function submitPassword(event: React.FormEvent) {
        event.preventDefault();
        passwordForm.post(loginWithPassword.url());
    }

    return (
        <>
            <Head title="Member Login" />

            <div className="flex flex-col gap-6">
                <div className="flex gap-2">
                    <button
                        type="button"
                        onClick={() => setMethod('password')}
                        className={`flex-1 rounded-md border px-3 py-2 text-sm ${
                            method === 'password'
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-input'
                        }`}
                    >
                        Customer ID + Password
                    </button>
                    <button
                        type="button"
                        onClick={() => setMethod('otp')}
                        className={`flex-1 rounded-md border px-3 py-2 text-sm ${
                            method === 'otp'
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-input'
                        }`}
                    >
                        One-Time Code
                    </button>
                </div>

                {method === 'password' && (
                    <form
                        onSubmit={submitPassword}
                        className="flex flex-col gap-4"
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="customer_id">Customer ID</Label>
                            <Input
                                id="customer_id"
                                value={passwordForm.data.customer_id}
                                onChange={(e) =>
                                    passwordForm.setData(
                                        'customer_id',
                                        e.target.value,
                                    )
                                }
                                placeholder="GWL01"
                                autoFocus
                                required
                            />
                            <InputError
                                message={passwordForm.errors.customer_id}
                            />
                        </div>
                        <div className="grid gap-2">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="password">Password</Label>
                                <TextLink
                                    href={passwordResetRoute.url()}
                                    className="text-sm"
                                >
                                    Forgot password?
                                </TextLink>
                            </div>
                            <PasswordInput
                                id="password"
                                value={passwordForm.data.password}
                                onChange={(e) =>
                                    passwordForm.setData(
                                        'password',
                                        e.target.value,
                                    )
                                }
                                required
                            />
                            <InputError
                                message={passwordForm.errors.password}
                            />
                        </div>
                        <Button
                            type="submit"
                            disabled={passwordForm.processing}
                        >
                            {passwordForm.processing && <Spinner />}
                            Log in
                        </Button>
                    </form>
                )}

                {method === 'otp' && !otpRequested && (
                    <form onSubmit={requestOtp} className="flex flex-col gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="identifier">
                                Mobile Number or Email
                            </Label>
                            <Input
                                id="identifier"
                                value={otpForm.data.identifier}
                                onChange={(e) =>
                                    otpForm.setData(
                                        'identifier',
                                        e.target.value,
                                    )
                                }
                                autoFocus
                                required
                            />
                            <InputError message={otpForm.errors.identifier} />
                        </div>
                        <Button type="submit" disabled={otpForm.processing}>
                            {otpForm.processing && <Spinner />}
                            Send Code
                        </Button>
                    </form>
                )}

                {method === 'otp' && otpRequested && (
                    <form onSubmit={verifyOtp} className="flex flex-col gap-4">
                        <p className="text-muted-foreground text-sm">
                            A code was sent to {otpForm.data.identifier}.
                        </p>
                        <div className="grid gap-2">
                            <Label htmlFor="otp">One-Time Code</Label>
                            <Input
                                id="otp"
                                value={otpForm.data.otp}
                                onChange={(e) =>
                                    otpForm.setData(
                                        'otp',
                                        e.target.value.replace(/\D/g, ''),
                                    )
                                }
                                maxLength={6}
                                autoFocus
                                required
                            />
                            <InputError message={otpForm.errors.otp} />
                        </div>
                        <Button type="submit" disabled={otpForm.processing}>
                            {otpForm.processing && <Spinner />}
                            Verify &amp; Log in
                        </Button>
                    </form>
                )}

                {status && (
                    <p className="text-center text-sm font-medium text-green-600">
                        {status}
                    </p>
                )}
            </div>
        </>
    );
}

GoldWaveLogin.layout = {
    title: 'GoldWave Member Login',
    description: 'Log in with your Customer ID or a one-time code.',
};

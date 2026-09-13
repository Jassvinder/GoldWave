import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { set as setPasswordRoute } from '@/routes/member/password';
import * as otp from '@/routes/member/password/otp';

type Props = {
    status?: string;
};

/**
 * DOMAIN_LOGIC.md §2.2 — password set/reset requires OTP first, with no
 * bypass: request OTP → verify OTP → set new password. Rendered inside
 * AuthLayout (name starts with `auth/`, see app.tsx) — no outer
 * centered/Card wrapper needed here.
 */
export default function GoldWavePasswordReset({ status }: Props) {
    const [step, setStep] = useState<'request' | 'verify' | 'set'>('request');

    const otpForm = useForm({ identifier: '', otp: '' });
    const passwordForm = useForm({ password: '', password_confirmation: '' });

    function requestOtp(event: React.FormEvent) {
        event.preventDefault();
        otpForm.post(otp.request.url(), {
            preserveScroll: true,
            onSuccess: () => setStep('verify'),
        });
    }

    function verifyOtp(event: React.FormEvent) {
        event.preventDefault();
        otpForm.post(otp.verify.url(), {
            preserveScroll: true,
            onSuccess: () => setStep('set'),
        });
    }

    function submitNewPassword(event: React.FormEvent) {
        event.preventDefault();
        passwordForm.post(setPasswordRoute.url());
    }

    return (
        <>
            <Head title="Reset Password" />

            <div className="flex flex-col gap-6">
                {step === 'request' && (
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

                {step === 'verify' && (
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
                            Verify Code
                        </Button>
                    </form>
                )}

                {step === 'set' && (
                    <form
                        onSubmit={submitNewPassword}
                        className="flex flex-col gap-4"
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="password">New Password</Label>
                            <PasswordInput
                                id="password"
                                value={passwordForm.data.password}
                                onChange={(e) =>
                                    passwordForm.setData(
                                        'password',
                                        e.target.value,
                                    )
                                }
                                autoFocus
                                required
                            />
                            <InputError
                                message={passwordForm.errors.password}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Confirm New Password
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                value={passwordForm.data.password_confirmation}
                                onChange={(e) =>
                                    passwordForm.setData(
                                        'password_confirmation',
                                        e.target.value,
                                    )
                                }
                                required
                            />
                            <InputError
                                message={
                                    passwordForm.errors.password_confirmation
                                }
                            />
                        </div>
                        <Button
                            type="submit"
                            disabled={passwordForm.processing}
                        >
                            {passwordForm.processing && <Spinner />}
                            Set Password
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

GoldWavePasswordReset.layout = {
    title: 'Reset Password',
    description: 'Verify a one-time code before setting a new password.',
};

import { Head, usePage, useForm } from '@inertiajs/react';
import { Check, Copy, Link2, ShieldCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import FormErrorSummary, {
    SponsorRequiredHint,
} from '@/components/form-error-summary';
import InputError from '@/components/input-error';
import { PlanPicker } from '@/components/plan-picker';
import type { Plan } from '@/components/plan-picker';
import {
    PaymentModePicker,
    type PaymentMode,
    type PaymentOptions,
} from '@/components/payment-mode-picker';
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
import { store, walletOtp } from '@/routes/member/assisted-registration';
import { validateSponsor } from '@/routes/registration';

type Props = {
    plans: Plan[];
    wallet_balance: number;
    /** T-162 — permanent `/join?ref=…` link with this member as sponsor. */
    referral_link: string;
    payment_options: PaymentOptions;
    /** T-215 — masked email the wallet-payment code goes to. */
    wallet_otp_email: string;
    /** T-215 — set during the 48-hour hold after an email/mobile change. */
    wallet_hold_message: string | null;
};

/** `sentTo` stays set after a failed resend, so the code input never disappears once a code was sent. */
type OtpState = {
    sending: boolean;
    sentTo: string | null;
    error: string | null;
};

function xsrfToken(): string {
    return decodeURIComponent(
        document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
    );
}

type SponsorState =
    | { status: 'idle' }
    | { status: 'checking' }
    | { status: 'valid'; name: string | null; customerId: string }
    | { status: 'invalid'; message: string };

/** DOMAIN_LOGIC.md §12.2(b) — T-153. Register a different, new member from your own portal; pay from your own wallet, optionally. */
export default function MemberAssistedRegistration({
    plans,
    wallet_balance,
    referral_link,
    payment_options,
    wallet_otp_email,
    wallet_hold_message,
}: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const [sponsor, setSponsor] = useState<SponsorState>({ status: 'idle' });
    const [copied, setCopied] = useState(false);
    const linkInput = useRef<HTMLInputElement>(null);
    const [otp, setOtp] = useState<OtpState>({
        sending: false,
        sentTo: null,
        error: null,
    });
    const [resendIn, setResendIn] = useState(0);

    useEffect(() => {
        if (resendIn <= 0) {
            return;
        }

        const timer = window.setTimeout(() => setResendIn((s) => s - 1), 1000);

        return () => window.clearTimeout(timer);
    }, [resendIn]);

    const form = useForm({
        sponsor_code: '',
        placement_side: 'left' as 'left' | 'right',
        name: '',
        gender: '' as 'male' | 'female' | 'other' | '',
        email: '',
        mobile: '',
        membership_plan_id: '' as number | '',
        payment_mode: 'cash' as PaymentMode,
        upi_reference: '',
        upi_screenshot: null as File | null,
        wallet_otp: '',
    });

    const payingByWallet = form.data.payment_mode === 'wallet';

    /** T-215 — sends the code that approves this wallet payment to the member's own registered email. */
    async function sendWalletOtp() {
        setOtp((current) => ({ ...current, sending: true, error: null }));

        let error: string;

        try {
            const response = await fetch(walletOtp.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({
                    name: form.data.name || null,
                    membership_plan_id: form.data.membership_plan_id || null,
                }),
            });

            const body = await response.json().catch(() => ({}));

            if (response.ok) {
                setOtp({ sending: false, sentTo: body.sent_to, error: null });
                setResendIn(60);

                return;
            }

            error =
                body.errors?.wallet_otp?.[0] ??
                (response.status === 429
                    ? 'Too many tries. Please wait a minute and try again.'
                    : 'The code could not be sent. Please try again.');
        } catch {
            error =
                'The code could not be sent. Check your connection and try again.';
        }

        setOtp((current) => ({ ...current, sending: false, error }));
    }

    async function checkSponsorCode(code: string) {
        if (!code) {
            setSponsor({ status: 'idle' });
            return;
        }

        setSponsor({ status: 'checking' });

        const response = await fetch(validateSponsor.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
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
        form.post(store.url(), { preserveScroll: 'errors' });
    }

    async function copyLink() {
        try {
            await navigator.clipboard.writeText(referral_link);
        } catch {
            // The Clipboard API needs HTTPS (or localhost); fall back to copying the selected field.
            linkInput.current?.select();
            document.execCommand('copy');
        }

        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
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
                        <CardTitle className="flex items-center gap-2">
                            <Link2 className="size-5" />
                            Your Registration Link
                        </CardTitle>
                        <CardDescription>
                            Share this link with anyone who wants to join under
                            you. It opens the join page with you already set as
                            their sponsor, so they fill in their own details and
                            pay. The link is permanent — it never changes.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col gap-2 sm:flex-row">
                            <Input
                                ref={linkInput}
                                value={referral_link}
                                readOnly
                                onFocus={(e) => e.target.select()}
                                aria-label="Your registration link"
                                className="font-mono text-sm"
                            />
                            <Button
                                type="button"
                                onClick={copyLink}
                                className="shrink-0"
                            >
                                {copied ? <Check /> : <Copy />}
                                {copied ? 'Copied' : 'Copy link'}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Register a New Member
                        </CardTitle>
                        <CardDescription>
                            This registration is for someone else joining
                            GoldWave — the sponsor still needs their own sponsor
                            code, same as the public join form. Your wallet
                            balance is ₹{wallet_balance.toFixed(2)} — you may
                            optionally pay their registration from it.
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

                            <PlanPicker
                                plans={plans}
                                value={form.data.membership_plan_id}
                                onChange={(id) => {
                                    form.setData('membership_plan_id', id);
                                    form.clearErrors('membership_plan_id');
                                }}
                                error={form.errors.membership_plan_id}
                            />

                            <div className="grid gap-2">
                                <Label>Payment Mode</Label>
                                <PaymentModePicker
                                    options={payment_options}
                                    amount={
                                        plans.find(
                                            (p) =>
                                                p.id ===
                                                form.data.membership_plan_id,
                                        )?.amount
                                    }
                                    mode={form.data.payment_mode}
                                    onModeChange={(mode) => {
                                        form.setData('payment_mode', mode);
                                        form.clearErrors(
                                            'payment_mode',
                                            'upi_reference',
                                            'upi_screenshot',
                                        );
                                    }}
                                    upiReference={form.data.upi_reference}
                                    onUpiReferenceChange={(value) =>
                                        form.setData('upi_reference', value)
                                    }
                                    onUpiScreenshotChange={(file) =>
                                        form.setData('upi_screenshot', file)
                                    }
                                    errors={{
                                        mode: form.errors.payment_mode,
                                        upi_reference:
                                            form.errors.upi_reference,
                                        upi_screenshot:
                                            form.errors.upi_screenshot,
                                    }}
                                    walletLabel="My Wallet"
                                    walletNote={
                                        <div className="flex flex-col gap-3">
                                            <p className="text-muted-foreground text-sm">
                                                The registration amount will be
                                                deducted from your own wallet
                                                balance (₹
                                                {wallet_balance.toFixed(2)}{' '}
                                                available) and the new member is
                                                activated immediately.
                                            </p>
                                            <WalletOtpBox
                                                holdMessage={
                                                    wallet_hold_message
                                                }
                                                email={wallet_otp_email}
                                                otp={otp}
                                                resendIn={resendIn}
                                                onSend={sendWalletOtp}
                                                code={form.data.wallet_otp}
                                                onCodeChange={(value) =>
                                                    form.setData(
                                                        'wallet_otp',
                                                        value,
                                                    )
                                                }
                                                error={form.errors.wallet_otp}
                                            />
                                        </div>
                                    }
                                />
                            </div>

                            <FormErrorSummary errors={form.errors} />
                            <Button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    sponsor.status !== 'valid' ||
                                    (payingByWallet &&
                                        (wallet_hold_message !== null ||
                                            form.data.wallet_otp.length !== 6))
                                }
                            >
                                {form.processing && <Spinner />}
                                Register
                            </Button>
                            <SponsorRequiredHint status={sponsor.status} />
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

/**
 * T-215 (DOMAIN_LOGIC.md §12.2(d)) — a wallet payment needs a 6-digit code sent to the member's own registered email,
 * so someone else signed in to this account cannot spend the wallet. During the 48-hour hold after an email/mobile
 * change, only the hold message is shown.
 */
function WalletOtpBox({
    holdMessage,
    email,
    otp,
    resendIn,
    onSend,
    code,
    onCodeChange,
    error,
}: {
    holdMessage: string | null;
    email: string;
    otp: OtpState;
    resendIn: number;
    onSend: () => void;
    code: string;
    onCodeChange: (value: string) => void;
    error?: string;
}) {
    if (holdMessage) {
        return (
            <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                {holdMessage}
            </div>
        );
    }

    const sent = otp.sentTo !== null;

    return (
        <div className="flex flex-col gap-3 rounded-md border p-3">
            <div className="flex items-start gap-2">
                <ShieldCheck className="text-primary mt-0.5 size-4 shrink-0" />
                <div className="flex flex-col gap-0.5">
                    <span className="text-sm font-medium">
                        Verify with a one-time code
                    </span>
                    <span className="text-muted-foreground text-sm">
                        {sent
                            ? `Code sent to ${otp.sentTo}. It is valid for 10 minutes.`
                            : `We will send a 6-digit code to ${email}.`}
                    </span>
                </div>
            </div>

            <div className="flex flex-col gap-2 sm:flex-row sm:items-start">
                {sent && (
                    <div className="grid gap-1 sm:flex-1">
                        <Label htmlFor="wallet_otp" className="sr-only">
                            One-time code
                        </Label>
                        <Input
                            id="wallet_otp"
                            value={code}
                            onChange={(e) =>
                                onCodeChange(e.target.value.replace(/\D/g, ''))
                            }
                            maxLength={6}
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            placeholder="6-digit code"
                            className="font-mono tracking-widest"
                            autoFocus
                        />
                    </div>
                )}
                <Button
                    type="button"
                    variant={sent ? 'outline' : 'default'}
                    onClick={onSend}
                    disabled={otp.sending || resendIn > 0}
                    className="shrink-0"
                >
                    {otp.sending && <Spinner />}
                    {sent
                        ? resendIn > 0
                            ? `Resend code in ${resendIn}s`
                            : 'Resend code'
                        : 'Send code'}
                </Button>
            </div>

            {otp.error && (
                <p className="text-destructive text-sm">{otp.error}</p>
            )}
            <InputError message={error} />
        </div>
    );
}

import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type PaymentMode = 'cash' | 'upi' | 'online' | 'wallet';

/** Page prop `payment_options` (PaymentModes::forPage()). */
export type PaymentOptions = {
    modes: PaymentMode[];
    upi_id: string | null;
    upi_qr_url: string | null;
};

type Props = {
    options: PaymentOptions;
    mode: PaymentMode;
    onModeChange: (mode: PaymentMode) => void;
    upiReference: string;
    onUpiReferenceChange: (value: string) => void;
    onUpiScreenshotChange: (file: File | null) => void;
    errors: {
        mode?: string;
        upi_reference?: string;
        upi_screenshot?: string;
    };
    /** Amount to pay, shown next to the QR. */
    amount?: string | number | null;
    /** e.g. "Store Wallet" or "My Wallet" — only when `wallet` is offered. */
    walletLabel?: string;
    /** Shown under the choice when Wallet is picked. */
    walletNote?: ReactNode;
    /** Prefix so two pickers on one page don't share element ids. */
    idPrefix?: string;
};

const LABELS: Record<PaymentMode, string> = {
    cash: 'Cash',
    upi: 'GPay / UPI',
    online: 'Online (card / netbanking)',
    wallet: 'Wallet',
};

/**
 * T-196 (DOMAIN_LOGIC.md §10) — the one payment-mode chooser for registration, assisted registration and EMI
 * payments. GPay/UPI shows the company QR + UPI ID and asks for the Transaction/UTR Ref ID and a payment screenshot
 * (both required); Cash and GPay/UPI wait for Super Admin / Admin approval, Wallet is confirmed at once.
 */
export function PaymentModePicker({
    options,
    mode,
    onModeChange,
    upiReference,
    onUpiReferenceChange,
    onUpiScreenshotChange,
    errors,
    amount,
    walletLabel,
    walletNote,
    idPrefix = 'pay',
}: Props) {
    const upiReady = options.upi_id !== null || options.upi_qr_url !== null;

    return (
        <div className="grid gap-3">
            <div className="flex flex-wrap gap-2">
                {options.modes.map((option) => (
                    <button
                        key={option}
                        type="button"
                        onClick={() => onModeChange(option)}
                        className={`min-w-28 flex-1 rounded-md border px-3 py-2 text-sm ${
                            mode === option
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-input bg-transparent'
                        }`}
                    >
                        {option === 'wallet' && walletLabel
                            ? walletLabel
                            : LABELS[option]}
                    </button>
                ))}
            </div>
            <InputError message={errors.mode} />

            {mode === 'cash' && (
                <p className="text-muted-foreground text-sm">
                    Pay in cash. The payment is confirmed once the company
                    approves it.
                </p>
            )}

            {mode === 'wallet' && walletNote}

            {mode === 'upi' && (
                <div className="flex flex-col gap-4 rounded-md border p-3 sm:flex-row">
                    {upiReady ? (
                        <div className="flex shrink-0 flex-col items-center gap-2">
                            {options.upi_qr_url && (
                                <img
                                    src={options.upi_qr_url}
                                    alt="Company UPI QR code"
                                    className="size-44 rounded-md border bg-white object-contain p-1"
                                />
                            )}
                            {options.upi_id && (
                                <span className="font-mono text-sm">
                                    {options.upi_id}
                                </span>
                            )}
                        </div>
                    ) : (
                        <p className="text-destructive text-sm">
                            The company UPI details are not set up yet — please
                            choose Cash or contact the company.
                        </p>
                    )}

                    <div className="grid flex-1 content-start gap-3">
                        <p className="text-sm">
                            Scan the QR with GPay / any UPI app
                            {amount ? (
                                <>
                                    {' '}
                                    and pay{' '}
                                    <span className="font-semibold">
                                        ₹
                                        {Number(amount).toLocaleString('en-IN')}
                                    </span>
                                </>
                            ) : null}
                            . Then enter the payment's Ref ID and upload its
                            screenshot. It is confirmed once the company checks
                            it.
                        </p>
                        <div className="grid gap-1.5">
                            <Label htmlFor={`${idPrefix}-upi-ref`}>
                                Transaction / UTR Ref ID
                            </Label>
                            <Input
                                id={`${idPrefix}-upi-ref`}
                                value={upiReference}
                                onChange={(e) =>
                                    onUpiReferenceChange(e.target.value)
                                }
                                placeholder="e.g. 427812345678"
                                required
                            />
                            <InputError message={errors.upi_reference} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor={`${idPrefix}-upi-shot`}>
                                Payment screenshot
                            </Label>
                            <Input
                                id={`${idPrefix}-upi-shot`}
                                type="file"
                                accept="image/*"
                                onChange={(e) =>
                                    onUpiScreenshotChange(
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                                required
                            />
                            <InputError message={errors.upi_screenshot} />
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

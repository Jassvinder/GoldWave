import { Check, Copy, Download, Smartphone } from 'lucide-react';
import { useEffect, useState } from 'react';
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
 * T-212 (06-10-2026): the GPay/UPI panel is stacked — QR centred on top, UPI ID with a Copy button, the amount, then
 * full-width Ref ID and screenshot inputs (the side-by-side layout squeezed the inputs inside the EMI dialog).
 * T-217 (13-10-2026): a member on a phone cannot scan a QR shown on that same phone, so phones also get a
 * "Pay with UPI app" button (a `upi://pay` link with the amount filled in) and every device gets "Save QR" (a PNG to
 * scan from the gallery in GPay). Some UPI apps refuse such links for a non-merchant UPI ID, so Save QR is the
 * fallback.
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
    const isPhone = useIsPhone();

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
                <div className="flex flex-col gap-4 rounded-md border p-3">
                    {upiReady ? (
                        <div className="flex flex-col items-center gap-3">
                            {options.upi_qr_url && (
                                <img
                                    src={options.upi_qr_url}
                                    alt="Company GPay / UPI QR code"
                                    className="w-56 max-w-full rounded-lg border bg-white object-contain shadow-sm"
                                />
                            )}
                            {options.upi_id && (
                                <UpiIdRow upiId={options.upi_id} />
                            )}
                            {amount ? (
                                <p className="text-sm">
                                    Amount to pay:{' '}
                                    <span className="text-base font-semibold">
                                        ₹
                                        {Number(amount).toLocaleString('en-IN')}
                                    </span>
                                </p>
                            ) : null}
                            <div className="flex w-full max-w-sm flex-col gap-2 sm:flex-row">
                                {isPhone && options.upi_id && (
                                    <a
                                        href={upiPayLink(
                                            options.upi_id,
                                            amount,
                                        )}
                                        className="bg-primary text-primary-foreground flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-2.5 text-sm font-medium shadow-sm"
                                    >
                                        <Smartphone className="size-4" />
                                        Pay with UPI app
                                    </a>
                                )}
                                {options.upi_qr_url && (
                                    <SaveQrButton url={options.upi_qr_url} />
                                )}
                            </div>
                        </div>
                    ) : (
                        <p className="text-destructive text-sm">
                            The company UPI details are not set up yet — please
                            choose Cash or contact the company.
                        </p>
                    )}

                    <div className="grid gap-3">
                        {isPhone && upiReady && (
                            <p className="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                                On this phone? Tap{' '}
                                <strong>Pay with UPI app</strong>. If it does
                                not work, tap <strong>Save QR</strong>, open
                                GPay → Scan → choose the QR from your gallery.
                                You can also pay to the UPI ID.
                            </p>
                        )}
                        <p className="text-muted-foreground text-sm">
                            Scan the QR with GPay or any UPI app (or pay to the
                            UPI ID), then enter the payment&apos;s Ref ID and
                            upload its screenshot. It is confirmed once the
                            company checks it.
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

/**
 * T-217 — true on Android / iPhone / iPad. Read after mount (pages are server-rendered, so the first render must match
 * the server's, which has no user agent).
 */
function useIsPhone(): boolean {
    const [isPhone, setIsPhone] = useState(false);

    useEffect(() => {
        const touchMac =
            navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1;
        setIsPhone(
            /Android|iPhone|iPad|iPod/i.test(navigator.userAgent) || touchMac,
        );
    }, []);

    return isPhone;
}

/** T-217 — a UPI intent link: the phone offers its UPI apps with the payee and amount filled in. */
function upiPayLink(upiId: string, amount?: string | number | null): string {
    const params = new URLSearchParams({
        pa: upiId,
        pn: import.meta.env.VITE_APP_NAME || 'GoldWave',
        cu: 'INR',
    });

    if (amount && Number(amount) > 0) {
        params.set('am', Number(amount).toFixed(2));
    }

    return `upi://pay?${params.toString()}`;
}

/**
 * T-217 — saves the company QR as a PNG with a white border (gallery scanners read it more reliably than the
 * transparent/WebP original). On iPhone the share sheet is used, because it offers "Save Image" to Photos, where a
 * plain download would land in Files. Falls back to the original file if the conversion fails.
 */
function SaveQrButton({ url }: { url: string }) {
    const [saving, setSaving] = useState(false);

    const save = async () => {
        setSaving(true);

        try {
            const blob = await qrAsPng(url);
            const file = new File([blob], 'goldwave-upi-qr.png', {
                type: 'image/png',
            });
            const isApple = /iPhone|iPad|iPod/i.test(navigator.userAgent);

            if (isApple && navigator.canShare?.({ files: [file] })) {
                await navigator.share({ files: [file] });
            } else {
                downloadHref(URL.createObjectURL(blob), file.name, true);
            }
        } catch (error) {
            // The person closing the share sheet is not a failure.
            if (
                !(error instanceof DOMException && error.name === 'AbortError')
            ) {
                downloadHref(url, 'goldwave-upi-qr', false);
            }
        } finally {
            setSaving(false);
        }
    };

    return (
        <button
            type="button"
            onClick={save}
            disabled={saving}
            className="border-input flex flex-1 items-center justify-center gap-2 rounded-md border px-3 py-2.5 text-sm font-medium disabled:opacity-60"
        >
            <Download className="size-4" />
            {saving ? 'Saving…' : 'Save QR'}
        </button>
    );
}

async function qrAsPng(url: string): Promise<Blob> {
    const img = new Image();
    img.src = url;
    await img.decode();

    const pad = Math.round(Math.max(img.naturalWidth, img.naturalHeight) / 12);
    const canvas = document.createElement('canvas');
    canvas.width = img.naturalWidth + pad * 2;
    canvas.height = img.naturalHeight + pad * 2;

    const ctx = canvas.getContext('2d');

    if (!ctx) {
        throw new Error('Canvas is not available.');
    }

    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(img, pad, pad);

    return new Promise((resolve, reject) =>
        canvas.toBlob(
            (blob) => (blob ? resolve(blob) : reject(new Error('No PNG.'))),
            'image/png',
        ),
    );
}

function downloadHref(href: string, name: string, revoke: boolean): void {
    const link = document.createElement('a');
    link.href = href;
    link.download = name;
    document.body.appendChild(link);
    link.click();
    link.remove();

    if (revoke) {
        window.setTimeout(() => URL.revokeObjectURL(href), 1000);
    }
}

/** T-212 — the company UPI ID with a Copy button, for paying from a UPI app without scanning. */
function UpiIdRow({ upiId }: { upiId: string }) {
    const [copied, setCopied] = useState(false);

    const copy = () => {
        navigator.clipboard
            ?.writeText(upiId)
            .then(() => {
                setCopied(true);
                window.setTimeout(() => setCopied(false), 2000);
            })
            .catch(() => setCopied(false));
    };

    return (
        <div className="flex items-center gap-2 rounded-md border bg-white px-3 py-1.5 dark:bg-transparent">
            <span className="text-muted-foreground text-xs">UPI ID</span>
            <span className="font-mono text-sm font-medium">{upiId}</span>
            <button
                type="button"
                onClick={copy}
                className="text-primary flex items-center gap-1 text-xs font-medium hover:underline"
                aria-label="Copy UPI ID"
            >
                {copied ? (
                    <>
                        <Check className="size-3.5" /> Copied
                    </>
                ) : (
                    <>
                        <Copy className="size-3.5" /> Copy
                    </>
                )}
            </button>
        </div>
    );
}

import { Head, useForm, usePage } from '@inertiajs/react';
import { QrCode } from 'lucide-react';
import type { FormEventHandler } from 'react';
import { FormSection } from '@/components/form-section';
import InputError from '@/components/input-error';
import type { PaymentOptions } from '@/components/payment-mode-picker';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/super-admin/payment-settings';

type Props = {
    settings: PaymentOptions;
    online_enabled: boolean;
};

/**
 * T-196 (30-09-2026) — the company GPay/UPI details members see on every payment step: UPI ID and the QR image to
 * scan. Members then enter the Ref ID and a screenshot, and Super Admin / Admin approve it on Payment Approvals.
 */
export default function PaymentSettings({ settings, online_enabled }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const { data, setData, post, processing, errors } = useForm({
        upi_id: settings.upi_id ?? '',
        upi_qr: null as File | null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(update.url(), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Payment Settings" />

            <form
                onSubmit={submit}
                className="mx-auto flex max-w-2xl flex-col gap-6 p-4"
            >
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <FormSection
                    icon={QrCode}
                    color="blue"
                    title="GPay / UPI"
                    description="Shown to members on registration and EMI payments. They pay to this UPI, then enter the Ref ID and upload a screenshot for approval."
                    contentClassName="flex flex-col gap-4 sm:flex-row"
                >
                    <div className="flex shrink-0 flex-col items-center gap-2">
                        {settings.upi_qr_url ? (
                            <img
                                src={settings.upi_qr_url}
                                alt="Current UPI QR code"
                                className="size-44 rounded-md border bg-white object-contain p-1"
                            />
                        ) : (
                            <div className="text-muted-foreground flex size-44 items-center justify-center rounded-md border border-dashed p-3 text-center text-xs">
                                No QR uploaded yet — GPay/UPI can't be used
                                until one is.
                            </div>
                        )}
                    </div>

                    <div className="grid flex-1 content-start gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="upi_id">Company UPI ID</Label>
                            <Input
                                id="upi_id"
                                value={data.upi_id}
                                onChange={(e) =>
                                    setData('upi_id', e.target.value)
                                }
                                placeholder="goldwave@okaxis"
                                required
                            />
                            <InputError message={errors.upi_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="upi_qr">
                                {settings.upi_qr_url
                                    ? 'Replace QR image (optional)'
                                    : 'QR image'}
                            </Label>
                            <Input
                                id="upi_qr"
                                type="file"
                                accept="image/*"
                                onChange={(e) =>
                                    setData('upi_qr', e.target.files?.[0] ?? null)
                                }
                                required={!settings.upi_qr_url}
                            />
                            <InputError message={errors.upi_qr} />
                        </div>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="self-start"
                        >
                            Save
                        </Button>
                    </div>
                </FormSection>

                <p className="text-muted-foreground flex items-center gap-2 text-sm">
                    Online payment (Razorpay):{' '}
                    <Badge variant={online_enabled ? 'default' : 'secondary'}>
                        {online_enabled ? 'On' : 'Off'}
                    </Badge>
                    — switched with PAYMENT_ONLINE_ENABLED on the server.
                </p>
            </form>
        </>
    );
}

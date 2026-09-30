import { useForm, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import {
    PaymentModePicker,
    type PaymentMode,
    type PaymentOptions,
} from '@/components/payment-mode-picker';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

type Props = {
    /** POST target — a single EMI or "Pay All Remaining EMIs". */
    url: string;
    amount: string | number;
    title: string;
    description: string;
    trigger: ReactNode;
    /** Extra lines shown above the payment choice, e.g. the Pay All summary. */
    children?: ReactNode;
};

/**
 * T-196 — EMI payment dialog: choose Cash or GPay/UPI (with the company QR, Ref ID and screenshot), then confirm.
 * Reads the page prop `payment_options` (PaymentModes::forPage()) so every Pay button on the EMI page offers the same
 * modes.
 */
export function EmiPayDialog({
    url,
    amount,
    title,
    description,
    trigger,
    children,
}: Props) {
    const options = usePage().props.payment_options as PaymentOptions;
    const [open, setOpen] = useState(false);
    const form = useForm({
        mode: (options.modes[0] ?? 'cash') as PaymentMode,
        upi_reference: '',
        upi_screenshot: null as File | null,
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();
        form.post(url, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    }

    const amountLabel = `₹${Number(amount).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>

                    {children}

                    <PaymentModePicker
                        options={options}
                        mode={form.data.mode}
                        onModeChange={(mode) => form.setData('mode', mode)}
                        upiReference={form.data.upi_reference}
                        onUpiReferenceChange={(value) =>
                            form.setData('upi_reference', value)
                        }
                        onUpiScreenshotChange={(file) =>
                            form.setData('upi_screenshot', file)
                        }
                        errors={{
                            mode: form.errors.mode,
                            upi_reference: form.errors.upi_reference,
                            upi_screenshot: form.errors.upi_screenshot,
                        }}
                        amount={amount}
                        idPrefix="emi-pay"
                    />

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.data.mode === 'online'
                                ? `Pay ${amountLabel}`
                                : `Submit ${amountLabel} for approval`}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

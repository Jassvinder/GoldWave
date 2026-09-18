import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { fail, process } from '@/routes/super-admin/payout-requests';

type Props = {
    payoutRequest: { id: number };
};

const METHODS = [
    { value: 'bank_transfer', label: 'Bank Transfer' },
    { value: 'gpay_upi', label: 'GPay / UPI' },
    { value: 'cheque', label: 'Cheque' },
    { value: 'in_app_provider', label: 'In-App Provider' },
];

/**
 * T-109 (17-09-2026) — records how a pending payout was actually paid
 * (`ProcessPayoutRequest`) or that the attempt failed (`FailPayoutRequest`,
 * which releases the wallet hold instead of confirming it) — both need the
 * same method/reference inputs, so one dialog offers both outcomes.
 */
export function ProcessPayoutDialog({ payoutRequest }: Props) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        method: '',
        reference: '',
    });

    const submit = (outcome: 'process' | 'fail') => () => {
        const url =
            outcome === 'process'
                ? process.url(payoutRequest.id)
                : fail.url(payoutRequest.id);

        post(url, { onSuccess: () => setOpen(false) });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (!next) reset();
            }}
        >
            <DialogTrigger asChild>
                <Button size="sm">Process</Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Record Payout Outcome</DialogTitle>
                </DialogHeader>
                <form className="flex flex-col gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="payout_method">Method</Label>
                        <Select
                            value={data.method}
                            onValueChange={(value) => setData('method', value)}
                        >
                            <SelectTrigger id="payout_method">
                                <SelectValue placeholder="Select method" />
                            </SelectTrigger>
                            <SelectContent>
                                {METHODS.map((method) => (
                                    <SelectItem
                                        key={method.value}
                                        value={method.value}
                                    >
                                        {method.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.method && (
                            <p className="text-destructive text-sm">
                                {errors.method}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="payout_reference">
                            Reference (optional)
                        </Label>
                        <Input
                            id="payout_reference"
                            value={data.reference}
                            onChange={(e) =>
                                setData('reference', e.target.value)
                            }
                            placeholder="UTR / transaction ID"
                        />
                        {errors.reference && (
                            <p className="text-destructive text-sm">
                                {errors.reference}
                            </p>
                        )}
                    </div>

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={processing || !data.method}
                            onClick={submit('fail')}
                        >
                            Mark Failed
                        </Button>
                        <Button
                            type="button"
                            disabled={processing || !data.method}
                            onClick={submit('process')}
                        >
                            Mark Processed
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

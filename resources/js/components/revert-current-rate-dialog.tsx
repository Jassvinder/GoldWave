import { useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
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
import { Label } from '@/components/ui/label';
import { revertCurrentRate } from '@/routes/super-admin/members';

/** Super Admin "Revert to Future Rate" for a member's Current Rate booking (DOMAIN_LOGIC.md §3.0) — a reason is mandatory and goes to the audit log. */
export function RevertCurrentRateDialog({ memberId }: { memberId: number }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm({ reason: '' });
    const errorList = errors as Record<string, string | undefined>;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(revertCurrentRate.url(memberId), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    reset();
                    clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Revert to Future Rate
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Revert to Future Rate</DialogTitle>
                    <DialogDescription>
                        Every unpaid EMI goes back to the plan&apos;s plain
                        amount and the locked rate and weight are removed. Paid
                        EMIs and due dates are not touched. The member can book
                        at the Current Rate again afterwards.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="revert_reason">Reason</Label>
                        <textarea
                            id="revert_reason"
                            value={data.reason}
                            onChange={(e) => setData('reason', e.target.value)}
                            placeholder="e.g. Member booked by mistake and asked us on the phone"
                            maxLength={500}
                            rows={3}
                            autoFocus
                            className="border-input bg-background placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                        />
                        {errors.reason && (
                            <p className="text-destructive text-sm">
                                {errors.reason}
                            </p>
                        )}
                        {errorList.revert && (
                            <p className="text-destructive text-sm">
                                {errorList.revert}
                            </p>
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Confirm revert
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

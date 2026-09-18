import { useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
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
import { reject } from '@/routes/super-admin/profile-change-requests';

type Props = {
    changeRequest: { id: number };
};

/** T-109 (17-09-2026) — `RejectProfileChangeRequest` requires a rejection reason, so Reject needs its own small dialog rather than a single-click button. */
export function RejectChangeRequestDialog({ changeRequest }: Props) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        rejection_reason: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(reject.url(changeRequest.id), {
            onSuccess: () => setOpen(false),
        });
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
                <Button size="sm" variant="outline">
                    Reject
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Reject Change Request</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="rejection_reason">Reason</Label>
                        <Input
                            id="rejection_reason"
                            value={data.rejection_reason}
                            onChange={(e) =>
                                setData('rejection_reason', e.target.value)
                            }
                        />
                        {errors.rejection_reason && (
                            <p className="text-destructive text-sm">
                                {errors.rejection_reason}
                            </p>
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={processing}
                        >
                            Reject
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

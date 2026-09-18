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
import { store } from '@/routes/super-admin/dummy-entry-assignment';

type Props = {
    dummy: {
        id: number;
        customer_id: string;
    };
};

/**
 * T-107 (17-09-2026) — replaces the old select-a-row-then-fill-a-separate-
 * card-below flow with a direct per-row Edit: open the dialog, fill in the
 * real leader's details, save — that's the whole assignment.
 */
export function AssignDummyEntryDialog({ dummy }: Props) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        member_id: dummy.id,
        name: '',
        email: '',
        mobile: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(store.url(), {
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
                <Button variant="outline" size="sm">
                    Edit
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>
                        Assign Leader — {dummy.customer_id}
                    </DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="assign_name">Name</Label>
                        <Input
                            id="assign_name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                        {errors.name && (
                            <p className="text-destructive text-sm">
                                {errors.name}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="assign_email">Email</Label>
                        <Input
                            id="assign_email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                        {errors.email && (
                            <p className="text-destructive text-sm">
                                {errors.email}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="assign_mobile">Mobile (optional)</Label>
                        <Input
                            id="assign_mobile"
                            value={data.mobile}
                            onChange={(e) => setData('mobile', e.target.value)}
                        />
                        {errors.mobile && (
                            <p className="text-destructive text-sm">
                                {errors.mobile}
                            </p>
                        )}
                    </div>

                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            Assign Leader
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

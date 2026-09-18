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
import { update } from '@/routes/super-admin/admin-users';

type Props = {
    admin: {
        id: number;
        name: string;
        email: string;
        mobile?: string | null;
    };
};

/** T-106 (17-09-2026) — Super Admin corrects an Admin's own name/email/mobile. */
export function EditAdminUserDialog({ admin }: Props) {
    const [open, setOpen] = useState(false);
    const { data, setData, patch, processing, errors, reset } = useForm({
        name: admin.name,
        email: admin.email,
        mobile: admin.mobile ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(update.url(admin.id), {
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
                    <DialogTitle>Edit Admin User</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="edit_admin_name">Name</Label>
                        <Input
                            id="edit_admin_name"
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
                        <Label htmlFor="edit_admin_email">Email</Label>
                        <Input
                            id="edit_admin_email"
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
                        <Label htmlFor="edit_admin_mobile">Mobile</Label>
                        <Input
                            id="edit_admin_mobile"
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
                            Save Changes
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

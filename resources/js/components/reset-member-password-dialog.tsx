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
import { resetPassword } from '@/routes/super-admin/members';

type Props = {
    member: { id: number };
};

/** T-111 (19-09-2026) — Super Admin can directly set a member's password, no email/OTP reset flow. Kept as its own small dialog, separate from EditMemberDialog. */
export function ResetMemberPasswordDialog({ member }: Props) {
    const [open, setOpen] = useState(false);
    const { data, setData, patch, processing, errors, reset } = useForm({
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(resetPassword.url(member.id), {
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
                    Reset Password
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Reset Password</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="password">New Password</Label>
                        <Input
                            id="password"
                            type="password"
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                        />
                        {errors.password && (
                            <p className="text-destructive text-sm">
                                {errors.password}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">
                            Confirm Password
                        </Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) =>
                                setData('password_confirmation', e.target.value)
                            }
                        />
                    </div>

                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            Reset Password
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

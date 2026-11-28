import { useForm } from '@inertiajs/react';
import { ArrowRightLeft } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordReconfirmField from '@/components/password-reconfirm-field';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { validateSponsor } from '@/routes/registration';
import { changePlacement } from '@/routes/super-admin/members';

/** `MemberManagementController::show`'s `change_placement` prop. */
export type ChangePlacementOptions = {
    /** An "Add ID" ID: the sponsor stays the person's first ID, only the place changes. */
    sponsor_fixed: boolean;
    /** Why it cannot be moved any more, or null. */
    blocker: string | null;
};

type CodeState =
    | { status: 'idle' }
    | { status: 'checking' }
    | { status: 'valid'; name: string | null }
    | { status: 'invalid' };

/**
 * DOMAIN_LOGIC.md §3.5 (T-252) — Super Admin corrects a wrong joining while the member is not active yet: a new sponsor
 * (or, for an "Add ID" ID, a new parent) and side. The member goes to the bottom of that side, as a new joining does.
 */
export function ChangePlacementDialog({
    memberId,
    options,
    currentSponsor,
    currentSide,
}: {
    memberId: number;
    options: ChangePlacementOptions;
    currentSponsor: string | null;
    currentSide: string | null;
}) {
    const [open, setOpen] = useState(false);
    const [code, setCode] = useState<CodeState>({ status: 'idle' });
    const initial = {
        code: options.sponsor_fixed ? '' : (currentSponsor ?? ''),
        placement_side: (currentSide === 'right' ? 'left' : 'right') as
            | 'left'
            | 'right',
        reason: '',
        confirm_password: '',
    };
    const form = useForm(initial);
    const codeLabel = options.sponsor_fixed
        ? 'Place under (Customer ID)'
        : 'Sponsor (Customer ID)';

    async function checkCode(value: string) {
        if (!value.trim()) {
            setCode({ status: 'idle' });
            return;
        }

        setCode({ status: 'checking' });

        const response = await fetch(validateSponsor.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                ),
            },
            body: JSON.stringify({ sponsor_code: value.trim() }),
        });
        const body = await response.json().catch(() => ({}));

        setCode(
            response.ok && body.valid
                ? { status: 'valid', name: body.sponsor_name ?? null }
                : { status: 'invalid' },
        );
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();
        form.post(changePlacement.url(memberId), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
                setCode({ status: 'idle' });
            },
        });
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);

                if (value) {
                    form.setData(initial);
                    setCode({ status: 'idle' });
                } else {
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <ArrowRightLeft />
                    {options.sponsor_fixed ? 'Change Place' : 'Change Sponsor / Side'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {options.sponsor_fixed
                            ? 'Change place'
                            : 'Change sponsor / side'}
                    </DialogTitle>
                    <DialogDescription>
                        Only before the payment is approved. The member goes to
                        the bottom of the chosen side, the same way a new
                        joining is placed.
                        {options.sponsor_fixed &&
                            ' This is an added ID, so its sponsor stays the first ID of the person.'}
                    </DialogDescription>
                </DialogHeader>

                {options.blocker ? (
                    <p className="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                        {options.blocker}
                    </p>
                ) : (
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="change-placement-code">
                                {codeLabel}
                            </Label>
                            <Input
                                id="change-placement-code"
                                value={form.data.code}
                                onChange={(e) => {
                                    form.setData('code', e.target.value);
                                    setCode({ status: 'idle' });
                                }}
                                onBlur={(e) => checkCode(e.target.value)}
                                placeholder="e.g. GWL01"
                                required
                            />
                            {code.status === 'checking' && (
                                <p className="text-muted-foreground text-sm">
                                    Checking…
                                </p>
                            )}
                            {code.status === 'valid' && (
                                <p className="text-sm text-green-600">
                                    {code.name ?? 'Member found'}
                                </p>
                            )}
                            {code.status === 'invalid' && (
                                <p className="text-destructive text-sm">
                                    No active member has this Customer ID.
                                </p>
                            )}
                            <InputError message={form.errors.code} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Side</Label>
                            <div className="flex gap-2">
                                {(['left', 'right'] as const).map((side) => (
                                    <button
                                        key={side}
                                        type="button"
                                        onClick={() =>
                                            form.setData('placement_side', side)
                                        }
                                        className={`flex-1 rounded-md border px-3 py-2 text-sm capitalize ${
                                            form.data.placement_side === side
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-input bg-transparent'
                                        }`}
                                    >
                                        {side}
                                    </button>
                                ))}
                            </div>
                            <InputError message={form.errors.placement_side} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="change-placement-reason">
                                Reason
                            </Label>
                            <textarea
                                id="change-placement-reason"
                                value={form.data.reason}
                                onChange={(e) =>
                                    form.setData('reason', e.target.value)
                                }
                                placeholder="e.g. Joined on the wrong side by mistake"
                                maxLength={500}
                                rows={2}
                                required
                                className="border-input bg-background placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                            />
                            <InputError message={form.errors.reason} />
                        </div>

                        <PasswordReconfirmField
                            id="change-placement-password"
                            value={form.data.confirm_password}
                            error={form.errors.confirm_password}
                            onChange={(value) =>
                                form.setData('confirm_password', value)
                            }
                        />

                        <DialogFooter>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner />}
                                Move member
                            </Button>
                        </DialogFooter>
                    </form>
                )}
            </DialogContent>
        </Dialog>
    );
}

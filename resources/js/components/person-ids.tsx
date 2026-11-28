import { useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { IdCountField } from '@/components/id-count-field';
import InputError from '@/components/input-error';
import {
    PaymentModePicker,
    type PaymentMode,
    type PaymentOptions,
} from '@/components/payment-mode-picker';
import { Badge } from '@/components/ui/badge';
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

/** One ID of the person (`App\Support\PersonIds::list`). */
export type PersonId = {
    id: number;
    customer_id: string | null;
    status: string;
    is_first: boolean;
    plan: string | null;
    sponsor: string | null;
    placement: string | null;
    joined_on: string | null;
    payment: {
        mode: string;
        cash_status: string | null;
        amount: string;
        combined_amount: string | null;
    } | null;
};

/** The "Add ID" form's options (`App\Support\PersonIds::addForm`). */
export type AddIdsForm = {
    limit: number;
    held: number;
    can_add: number;
    plans: {
        id: number;
        name: string;
        amount: string;
        installment_count: number | null;
        product_category: string | null;
    }[];
    payment_options: PaymentOptions;
};

const STATUS_LABEL: Record<string, string> = {
    active: 'Active',
    payment_pending: 'Payment pending',
    draft: 'Draft',
    cancelled: 'Cancelled',
};

const inr = (value: string | number) =>
    `₹${Number(value).toLocaleString('en-IN')}`;

/** DOMAIN_LOGIC.md §3.4 (T-251) — every ID one person holds, first one first. */
export function PersonIdsTable({ ids }: { ids: PersonId[] }) {
    return (
        <div className="overflow-x-auto rounded-md border">
            <table className="w-full text-sm">
                <thead className="bg-muted/50 text-muted-foreground text-left text-xs">
                    <tr>
                        <th className="px-3 py-2 font-medium">#</th>
                        <th className="px-3 py-2 font-medium">Customer ID</th>
                        <th className="px-3 py-2 font-medium">Plan</th>
                        <th className="px-3 py-2 font-medium">Sponsor</th>
                        <th className="px-3 py-2 font-medium">Placed under</th>
                        <th className="px-3 py-2 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    {ids.map((id, index) => (
                        <tr key={id.id} className="border-t">
                            <td className="text-muted-foreground px-3 py-2">
                                {index + 1}
                            </td>
                            <td className="px-3 py-2">
                                <span className="font-mono font-semibold">
                                    {id.customer_id ?? '—'}
                                </span>
                                {id.is_first && (
                                    <Badge
                                        variant="outline"
                                        className="ml-2 text-[10px]"
                                    >
                                        First ID
                                    </Badge>
                                )}
                            </td>
                            <td className="px-3 py-2">{id.plan ?? '—'}</td>
                            <td className="px-3 py-2 font-mono text-xs">
                                {id.sponsor ?? '—'}
                            </td>
                            <td className="px-3 py-2 font-mono text-xs whitespace-nowrap">
                                {id.placement ?? '—'}
                            </td>
                            <td className="px-3 py-2">
                                <Badge
                                    variant={
                                        id.status === 'active'
                                            ? 'success'
                                            : 'secondary'
                                    }
                                >
                                    {STATUS_LABEL[id.status] ?? id.status}
                                </Badge>
                                {id.status === 'payment_pending' &&
                                    id.payment && (
                                        <div className="text-muted-foreground mt-1 text-xs">
                                            {id.payment.combined_amount
                                                ? `Part of ${inr(id.payment.combined_amount)} paid together`
                                                : inr(id.payment.amount)}{' '}
                                            ·{' '}
                                            {id.payment.cash_status ===
                                            'rejected'
                                                ? 'rejected'
                                                : 'awaiting approval'}
                                        </div>
                                    )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

type ParentState =
    | { status: 'idle' }
    | { status: 'checking' }
    | { status: 'valid'; name: string | null }
    | { status: 'invalid'; message: string };

/**
 * DOMAIN_LOGIC.md §3.4 (T-251) — "Add ID" for an existing person: how many, the plan, where the first new ID goes
 * (any active member + side; the rest fill the places under it), and one Cash or GPay/UPI payment for all of them.
 * Every new ID is sponsored by the person's first ID.
 */
export function AddIdsDialog({
    form: options,
    submitUrl,
    firstId,
}: {
    form: AddIdsForm;
    submitUrl: string;
    /** The person's first Customer ID, which sponsors every new ID. */
    firstId: string | null;
}) {
    const [open, setOpen] = useState(false);
    const [parent, setParent] = useState<ParentState>({ status: 'idle' });
    const form = useForm({
        id_count: 1,
        membership_plan_id: '' as number | '',
        parent_code: '',
        placement_side: 'left' as 'left' | 'right',
        payment_mode: 'cash' as PaymentMode,
        upi_reference: '',
        upi_screenshot: null as File | null,
    });

    const plan = options.plans.find(
        (p) => p.id === form.data.membership_plan_id,
    );
    const total = plan ? Number(plan.amount) * form.data.id_count : undefined;

    async function checkParent(code: string) {
        if (!code.trim()) {
            setParent({ status: 'idle' });
            return;
        }

        setParent({ status: 'checking' });

        const response = await fetch(validateSponsor.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                ),
            },
            body: JSON.stringify({ sponsor_code: code.trim() }),
        });
        const body = await response.json().catch(() => ({}));

        setParent(
            response.ok && body.valid
                ? { status: 'valid', name: body.sponsor_name ?? null }
                : {
                      status: 'invalid',
                      message: 'No active member has this Customer ID.',
                  },
        );
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();
        form.post(submitUrl, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
                setParent({ status: 'idle' });
            },
        });
    }

    if (options.can_add < 1) {
        return (
            <p className="text-muted-foreground text-sm">
                This person already holds {options.held} of the{' '}
                {options.limit} IDs allowed.
            </p>
        );
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);

                if (!value) {
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button>
                    <Plus />
                    Add ID
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Add ID</DialogTitle>
                    <DialogDescription>
                        Holds {options.held} of {options.limit} IDs; up to{' '}
                        {options.can_add} more. Every new ID is sponsored by the
                        first ID{firstId ? ` (${firstId})` : ''} and uses the
                        same login.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="add-id-plan">Plan</Label>
                        <select
                            id="add-id-plan"
                            className="border-input bg-background rounded-md border px-3 py-2 text-sm"
                            value={form.data.membership_plan_id}
                            onChange={(e) =>
                                form.setData(
                                    'membership_plan_id',
                                    e.target.value === ''
                                        ? ''
                                        : Number(e.target.value),
                                )
                            }
                            required
                        >
                            <option value="">Select a plan</option>
                            {options.plans.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name} —{' '}
                                    {p.installment_count
                                        ? `${inr(p.amount)} × ${p.installment_count} months`
                                        : `${inr(p.amount)} one-time`}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.membership_plan_id} />
                    </div>

                    <IdCountField
                        id="add-id-count"
                        value={form.data.id_count}
                        max={options.can_add}
                        amount={plan?.amount}
                        onChange={(count) => form.setData('id_count', count)}
                        error={form.errors.id_count}
                        placementNote="The first new ID goes under the member chosen below; the others are placed under it."
                    />

                    <div className="grid gap-2">
                        <Label htmlFor="add-id-parent">
                            Place under (Customer ID)
                        </Label>
                        <Input
                            id="add-id-parent"
                            value={form.data.parent_code}
                            onChange={(e) =>
                                form.setData('parent_code', e.target.value)
                            }
                            onBlur={(e) => checkParent(e.target.value)}
                            placeholder="Any member, e.g. GWL01"
                            required
                        />
                        {parent.status === 'checking' && (
                            <p className="text-muted-foreground text-sm">
                                Checking…
                            </p>
                        )}
                        {parent.status === 'valid' && (
                            <p className="text-sm text-green-600">
                                {parent.name ?? 'Member found'}
                            </p>
                        )}
                        {parent.status === 'invalid' && (
                            <p className="text-destructive text-sm">
                                {parent.message}
                            </p>
                        )}
                        <InputError message={form.errors.parent_code} />
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
                        <p className="text-muted-foreground text-xs">
                            If that side is taken, the ID goes further down the
                            same side.
                        </p>
                        <InputError message={form.errors.placement_side} />
                    </div>

                    <div className="grid gap-2">
                        <Label>Payment Mode</Label>
                        <PaymentModePicker
                            idPrefix="add-id"
                            options={options.payment_options}
                            amount={total}
                            mode={form.data.payment_mode}
                            onModeChange={(mode) => {
                                form.setData('payment_mode', mode);
                                form.clearErrors(
                                    'payment_mode',
                                    'upi_reference',
                                    'upi_screenshot',
                                );
                            }}
                            upiReference={form.data.upi_reference}
                            onUpiReferenceChange={(value) =>
                                form.setData('upi_reference', value)
                            }
                            onUpiScreenshotChange={(file) =>
                                form.setData('upi_screenshot', file)
                            }
                            errors={{
                                mode: form.errors.payment_mode,
                                upi_reference: form.errors.upi_reference,
                                upi_screenshot: form.errors.upi_screenshot,
                            }}
                        />
                    </div>

                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {total !== undefined
                                ? `Add ${form.data.id_count === 1 ? '1 ID' : `${form.data.id_count} IDs`} · ${inr(total)}`
                                : 'Add ID'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

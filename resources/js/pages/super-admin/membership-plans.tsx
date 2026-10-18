import { Head, useForm, usePage } from '@inertiajs/react';
import { History, Info, Package, Pencil, Plus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { FormSection } from '@/components/form-section';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { formatDate } from '@/lib/utils';
import { store, update } from '@/routes/super-admin/membership-plans';

type PlanType = 'emi' | 'direct';
type Metal = 'silver' | 'gold';

type Plan = {
    id: number;
    code: string;
    name: string;
    product_category: Metal | null;
    plan_type: PlanType;
    amount: number;
    installment_count: number | null;
    fixed_weight_grams: number | null;
    is_active: boolean;
    pair_emis: number | null;
    draw_emis: number | null;
    members_count: number;
    is_dummy_plan: boolean;
};

type Snapshot = {
    name: string;
    amount: number;
    installment_count: number | null;
    fixed_weight_grams: number | null;
    is_active: boolean;
    pair_emis: number | null;
    draw_emis: number | null;
};

type HistoryRow = {
    id: number;
    plan_name: string;
    action: 'created' | 'updated';
    before: Snapshot | null;
    after: Snapshot;
    changed_by: string;
    changed_on: string | null;
    changed_time: string | null;
};

type Props = {
    plans: Plan[];
    history: HistoryRow[];
};

const rupees = (value: number) => `₹${value.toLocaleString('en-IN')}`;

/** "₹2,000 × 10 months" for an EMI plan, "₹30,000 one-time" for a Direct plan. */
function priceLabel(
    amount: number,
    installments: number | null | undefined,
): string {
    return installments
        ? `${rupees(amount)} × ${installments} months`
        : `${rupees(amount)} one-time`;
}

const FIELD_LABELS: Record<keyof Snapshot, string> = {
    name: 'Name',
    amount: 'Amount',
    installment_count: 'EMIs',
    fixed_weight_grams: 'Booking weight',
    is_active: 'Status',
    pair_emis: 'EMIs for Pair',
    draw_emis: 'EMIs for Draw',
};

function showValue(
    field: keyof Snapshot,
    value: Snapshot[keyof Snapshot],
): string {
    if (value === null || value === undefined) {
        return '—';
    }

    if (field === 'amount') {
        return rupees(Number(value));
    }

    if (field === 'fixed_weight_grams') {
        return `${Number(value)} g`;
    }

    if (field === 'is_active') {
        return value ? 'On' : 'Off';
    }

    return String(value);
}

/**
 * T-237 (DOMAIN_LOGIC.md §3.3) — add or edit one plan. Metal and EMI/Direct are chosen only when adding; an edit shows
 * them read-only. EMI plans also need EMI count, the Current Rate Booking weight and the Pair/Draw EMIs.
 */
function PlanDialog({ plan }: { plan?: Plan }) {
    const isNew = plan === undefined;
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: plan?.name ?? '',
        product_category: (plan?.product_category ?? 'silver') as Metal,
        plan_type: (plan?.plan_type ?? 'emi') as PlanType,
        amount: plan ? String(plan.amount) : '',
        installment_count: plan?.installment_count
            ? String(plan.installment_count)
            : '',
        fixed_weight_grams: plan?.fixed_weight_grams
            ? String(plan.fixed_weight_grams)
            : '',
        pair_emis:
            plan?.pair_emis !== null && plan?.pair_emis !== undefined
                ? String(plan.pair_emis)
                : '',
        draw_emis:
            plan?.draw_emis !== null && plan?.draw_emis !== undefined
                ? String(plan.draw_emis)
                : '',
        is_active: plan?.is_active ?? true,
    });

    const isEmi = form.data.plan_type === 'emi';
    const amount = Number(form.data.amount);
    const installments = Number(form.data.installment_count);
    const total =
        isEmi && amount > 0 && installments > 0 ? amount * installments : null;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        // Only the fields that apply are sent: the server refuses metal/type on an edit and EMI fields on a Direct plan.
        form.transform((data) => ({
            name: data.name,
            amount: data.amount,
            is_active: data.is_active,
            ...(isNew
                ? {
                      product_category: data.product_category,
                      plan_type: data.plan_type,
                  }
                : {}),
            ...(isEmi
                ? {
                      installment_count: data.installment_count,
                      fixed_weight_grams: data.fixed_weight_grams,
                      pair_emis: data.pair_emis,
                      draw_emis: data.draw_emis,
                  }
                : {}),
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);

                if (isNew) {
                    form.reset();
                }
            },
        };

        if (isNew) {
            form.post(store.url(), options);
        } else {
            form.patch(update.url(plan.id), options);
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);

                if (!value) {
                    form.reset();
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                {isNew ? (
                    <Button>
                        <Plus />
                        Add plan
                    </Button>
                ) : (
                    <Button size="sm" variant="outline">
                        <Pencil />
                        Edit
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {isNew ? 'Add a plan' : `Edit ${plan.name}`}
                    </DialogTitle>
                    <DialogDescription>
                        {isNew
                            ? 'The plan gets the next free code automatically. Metal and EMI/Direct cannot be changed later.'
                            : `${plan.product_category === 'gold' ? 'Gold' : 'Silver'} · ${plan.plan_type === 'emi' ? 'EMI' : 'Direct (one-time)'} · code ${plan.code}`}
                    </DialogDescription>
                </DialogHeader>

                {!isNew && (
                    <p className="flex gap-2 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200">
                        <Info className="mt-0.5 size-4 shrink-0" />
                        <span>
                            Changes apply to new registrations only.{' '}
                            {plan.members_count > 0
                                ? `The ${plan.members_count} member${plan.members_count === 1 ? '' : 's'} already on this plan keep their own amount, EMIs and weight.`
                                : 'No member is on this plan yet.'}{' '}
                            A new name shows for everyone.
                        </span>
                    </p>
                )}

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor={`plan-name-${plan?.id ?? 'new'}`}>
                            Name
                        </Label>
                        <Input
                            id={`plan-name-${plan?.id ?? 'new'}`}
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="e.g. Silver Prime"
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    {isNew && (
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <ChoiceGroup
                                label="Metal"
                                value={form.data.product_category}
                                options={[
                                    { value: 'silver', label: 'Silver' },
                                    { value: 'gold', label: 'Gold' },
                                ]}
                                onChange={(v) =>
                                    form.setData('product_category', v as Metal)
                                }
                                error={form.errors.product_category}
                            />
                            <ChoiceGroup
                                label="Type"
                                value={form.data.plan_type}
                                options={[
                                    { value: 'emi', label: 'EMI' },
                                    { value: 'direct', label: 'Direct' },
                                ]}
                                onChange={(v) =>
                                    form.setData('plan_type', v as PlanType)
                                }
                                error={form.errors.plan_type}
                            />
                        </div>
                    )}

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <NumberField
                            id={`plan-amount-${plan?.id ?? 'new'}`}
                            label={
                                isEmi
                                    ? 'Monthly EMI (₹)'
                                    : 'One-time amount (₹)'
                            }
                            value={form.data.amount}
                            onChange={(v) => form.setData('amount', v)}
                            error={form.errors.amount}
                            step="0.01"
                        />
                        {isEmi && (
                            <NumberField
                                id={`plan-emis-${plan?.id ?? 'new'}`}
                                label="Number of EMIs"
                                value={form.data.installment_count}
                                onChange={(v) =>
                                    form.setData('installment_count', v)
                                }
                                error={form.errors.installment_count}
                            />
                        )}
                    </div>

                    {total !== null && (
                        <p className="text-muted-foreground -mt-2 text-sm">
                            Total {rupees(total)} over {installments} months.
                        </p>
                    )}

                    {isEmi && (
                        <>
                            <NumberField
                                id={`plan-weight-${plan?.id ?? 'new'}`}
                                label="Current Rate Booking weight (grams)"
                                value={form.data.fixed_weight_grams}
                                onChange={(v) =>
                                    form.setData('fixed_weight_grams', v)
                                }
                                error={form.errors.fixed_weight_grams}
                                step="0.001"
                                hint="Used only when a member books at the Current Rate. It is not shown on registration."
                            />
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <NumberField
                                    id={`plan-pair-${plan?.id ?? 'new'}`}
                                    label="EMIs needed for Pair"
                                    value={form.data.pair_emis}
                                    onChange={(v) =>
                                        form.setData('pair_emis', v)
                                    }
                                    error={form.errors.pair_emis}
                                />
                                <NumberField
                                    id={`plan-draw-${plan?.id ?? 'new'}`}
                                    label="EMIs needed for Draw"
                                    value={form.data.draw_emis}
                                    onChange={(v) =>
                                        form.setData('draw_emis', v)
                                    }
                                    error={form.errors.draw_emis}
                                />
                            </div>
                            <p className="text-muted-foreground -mt-2 text-xs">
                                A member counts for Pair and Draw after paying
                                this many EMIs. Changing either publishes a new
                                rule version.
                            </p>
                        </>
                    )}

                    <div className="grid gap-1.5">
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id={`plan-active-${plan?.id ?? 'new'}`}
                                checked={form.data.is_active}
                                onCheckedChange={(checked) =>
                                    form.setData('is_active', checked === true)
                                }
                            />
                            <Label htmlFor={`plan-active-${plan?.id ?? 'new'}`}>
                                Offered on registration (On)
                            </Label>
                        </div>
                        {!form.data.is_active && plan?.is_dummy_plan && (
                            <p className="text-sm text-amber-700 dark:text-amber-300">
                                Daily Dummy Entries use this plan. Choose
                                another plan on Dummy Entry Settings first.
                            </p>
                        )}
                        <InputError message={form.errors.is_active} />
                    </div>

                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {isNew ? 'Add plan' : 'Save changes'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function NumberField({
    id,
    label,
    value,
    onChange,
    error,
    step = '1',
    hint,
}: {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    step?: string;
    hint?: string;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                type="number"
                inputMode="decimal"
                min="0"
                step={step}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                required
            />
            {hint && <p className="text-muted-foreground text-xs">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

function ChoiceGroup({
    label,
    value,
    options,
    onChange,
    error,
}: {
    label: string;
    value: string;
    options: { value: string; label: string }[];
    onChange: (value: string) => void;
    error?: string;
}) {
    return (
        <div className="grid gap-1.5">
            <Label>{label}</Label>
            <div className="flex gap-2">
                {options.map((option) => (
                    <button
                        key={option.value}
                        type="button"
                        onClick={() => onChange(option.value)}
                        aria-pressed={value === option.value}
                        className={`flex-1 rounded-md border px-3 py-2 text-sm ${
                            value === option.value
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-input bg-transparent'
                        }`}
                    >
                        {option.label}
                    </button>
                ))}
            </div>
            <InputError message={error} />
        </div>
    );
}

const planColumns: DataTableColumn<Plan>[] = [
    {
        key: 'plan',
        header: 'Plan',
        render: (row) => (
            <div>
                <div className="font-medium">{row.name}</div>
                <div className="text-muted-foreground text-xs">
                    Code {row.code}
                </div>
            </div>
        ),
    },
    {
        key: 'type',
        header: 'Type',
        render: (row) => (
            <div className="flex flex-wrap gap-1.5">
                <Badge
                    className={
                        row.product_category === 'gold'
                            ? 'border-amber-500/40 bg-amber-500/15 text-amber-800 dark:text-amber-300'
                            : 'border-slate-400/40 bg-slate-400/15 text-slate-700 dark:text-slate-300'
                    }
                >
                    {row.product_category === 'gold' ? 'Gold' : 'Silver'}
                </Badge>
                <Badge variant="outline">
                    {row.plan_type === 'emi' ? 'EMI' : 'Direct'}
                </Badge>
            </div>
        ),
    },
    {
        key: 'price',
        header: 'Price',
        render: (row) => (
            <span className="whitespace-nowrap">
                {priceLabel(row.amount, row.installment_count)}
            </span>
        ),
    },
    {
        key: 'weight',
        header: 'Booking weight',
        render: (row) =>
            row.fixed_weight_grams !== null
                ? `${row.fixed_weight_grams} g`
                : '—',
    },
    {
        key: 'thresholds',
        header: 'Pair / Draw',
        render: (row) =>
            row.plan_type === 'emi' ? (
                <span className="whitespace-nowrap">
                    {row.pair_emis ?? '—'} / {row.draw_emis ?? '—'} EMIs
                </span>
            ) : (
                <span className="text-muted-foreground text-sm">
                    On joining
                </span>
            ),
    },
    {
        key: 'members',
        header: 'Members',
        render: (row) => row.members_count.toLocaleString('en-IN'),
    },
    {
        key: 'status',
        header: 'Status',
        render: (row) => (
            <div className="flex flex-wrap gap-1.5">
                <Badge variant={row.is_active ? 'success' : 'secondary'}>
                    {row.is_active ? 'On' : 'Off'}
                </Badge>
                {row.is_dummy_plan && (
                    <Badge variant="outline">Dummy entries</Badge>
                )}
            </div>
        ),
    },
];

const historyColumns: DataTableColumn<HistoryRow>[] = [
    {
        key: 'when',
        header: 'When',
        render: (row) => (
            <div className="whitespace-nowrap">
                <div className="font-medium">{formatDate(row.changed_on)}</div>
                <div className="text-muted-foreground text-xs">
                    {row.changed_time} · {row.changed_by}
                </div>
            </div>
        ),
    },
    {
        key: 'plan',
        header: 'Plan',
        render: (row) => (
            <div className="flex flex-wrap items-center gap-1.5">
                <span className="font-medium">{row.plan_name}</span>
                <Badge
                    variant={row.action === 'created' ? 'success' : 'outline'}
                >
                    {row.action === 'created' ? 'Added' : 'Edited'}
                </Badge>
            </div>
        ),
    },
    {
        key: 'changes',
        header: 'Changes',
        render: (row) => <ChangeList row={row} />,
    },
];

function ChangeList({ row }: { row: HistoryRow }) {
    const fields = (Object.keys(FIELD_LABELS) as (keyof Snapshot)[]).filter(
        (field) =>
            row.before === null
                ? row.after[field] !== null
                : row.before[field] !== row.after[field],
    );

    if (fields.length === 0) {
        return <span className="text-muted-foreground text-sm">No change</span>;
    }

    return (
        <ul className="space-y-0.5 text-sm">
            {fields.map((field) => (
                <li key={field} className="whitespace-normal">
                    <span className="text-muted-foreground">
                        {FIELD_LABELS[field]}:
                    </span>{' '}
                    {row.before !== null && (
                        <>{showValue(field, row.before[field])} → </>
                    )}
                    <span className="font-medium">
                        {showValue(field, row.after[field])}
                    </span>
                </li>
            ))}
        </ul>
    );
}

/**
 * T-237 (DOMAIN_LOGIC.md §3.3) — Super Admin's Membership Plans page: every plan with its price, booking weight,
 * Pair/Draw EMIs and member count; add or edit a plan; the full change history. Edits apply to new registrations
 * only.
 */
export default function MembershipPlans({ plans, history }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;

    return (
        <>
            <Head title="Membership Plans" />

            <div className="flex w-full flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200">
                        {flash.status}
                    </p>
                )}

                <FormSection
                    icon={Package}
                    color="amber"
                    title="Membership Plans"
                    description="Prices, EMIs and booking weight apply to new registrations only — members who already joined keep their own figures. A plan cannot be deleted; switch it Off to stop offering it."
                    contentClassName="flex flex-col gap-4"
                >
                    <div>
                        <PlanDialog />
                    </div>
                    <DataTable
                        columns={planColumns}
                        rows={plans}
                        rowKey={(row) => row.id}
                        emptyMessage="No plans yet."
                        renderActions={(row) => (
                            <PlanDialog
                                key={`${row.id}-${row.name}-${row.amount}-${row.installment_count}-${row.fixed_weight_grams}-${row.is_active}-${row.pair_emis}-${row.draw_emis}`}
                                plan={row}
                            />
                        )}
                    />
                </FormSection>

                <FormSection
                    icon={History}
                    color="purple"
                    title="History"
                    description="Every plan added or edited, newest first (last 100)."
                >
                    <DataTable
                        columns={historyColumns}
                        rows={history}
                        rowKey={(row) => row.id}
                        emptyMessage="No plan has been added or edited yet."
                    />
                </FormSection>
            </div>
        </>
    );
}

import { Crown, Gem } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

export type Plan = {
    id: number;
    code: string;
    name: string;
    amount: string;
    installment_count: number | null;
    product_category: 'gold' | 'silver';
    fixed_weight_grams: string | null;
};

type Props = {
    plans: Plan[];
    value: number | '';
    onChange: (planId: number) => void;
    error?: string;
};

type Metal = Plan['product_category'];

const METALS: {
    metal: Metal;
    label: string;
    placeholder: string;
    icon: LucideIcon;
    iconClass: string;
    activeClass: string;
    /** Highlighted/selected option — a light tint so both lines stay readable (the theme's teal accent hid the price line, 06-10-2026). */
    itemClass: string;
}[] = [
    {
        metal: 'silver',
        label: 'Silver plans',
        placeholder: 'Choose a silver plan',
        icon: Gem,
        iconClass:
            'bg-indigo-100 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-300',
        activeClass:
            'border-indigo-400 ring-2 ring-indigo-100 dark:ring-indigo-900',
        itemClass:
            'focus:bg-indigo-50 focus:text-indigo-950 data-[state=checked]:bg-indigo-50 dark:focus:bg-indigo-950 dark:focus:text-indigo-50 dark:data-[state=checked]:bg-indigo-950',
    },
    {
        metal: 'gold',
        label: 'Gold plans',
        placeholder: 'Choose a gold plan',
        icon: Crown,
        iconClass:
            'bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-300',
        activeClass:
            'border-amber-400 ring-2 ring-amber-100 dark:ring-amber-900',
        itemClass:
            'focus:bg-amber-50 focus:text-amber-950 data-[state=checked]:bg-amber-50 dark:focus:bg-amber-950 dark:focus:text-amber-50 dark:data-[state=checked]:bg-amber-950',
    },
];

function rupees(value: string | number): string {
    return `₹${Number(value).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;
}

/** "₹1,000 × 20 months" or "₹20,000 one-time". */
function schedule(plan: Plan): string {
    return plan.installment_count
        ? `${rupees(plan.amount)} × ${plan.installment_count} months`
        : `${rupees(plan.amount)} one-time`;
}

function entitlement(plan: Plan): string {
    const metal = plan.product_category === 'gold' ? 'Gold' : 'Silver';

    return plan.fixed_weight_grams
        ? `${Number(plan.fixed_weight_grams)} gm ${metal} Jewellery`
        : `${metal} jewellery of your choice at the current rate`;
}

/**
 * T-211 (06-10-2026, client request) — the plan step of every registration form (`/join`, member and admin assisted
 * registration): one dropdown for Silver plans and one for Gold plans instead of six cards. Choosing a plan in one
 * dropdown clears the other, since a member joins on exactly one plan. Each option shows the plan name and its
 * amount × months (or one-time); the chosen plan's details appear in the summary card below, which is built from rows
 * so more information can be added there without touching the dropdowns.
 */
export function PlanPicker({ plans, value, onChange, error }: Props) {
    const selected = plans.find((plan) => plan.id === value) ?? null;

    return (
        <div className="grid gap-2">
            <Label>Membership Plan</Label>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                {METALS.map((group) => {
                    const options = plans.filter(
                        (plan) => plan.product_category === group.metal,
                    );
                    const chosen =
                        selected?.product_category === group.metal
                            ? selected
                            : null;

                    if (options.length === 0) {
                        return null;
                    }

                    return (
                        <div key={group.metal} className="grid gap-1.5">
                            <span className="text-muted-foreground flex items-center gap-1.5 text-xs font-medium">
                                <span
                                    className={cn(
                                        'flex size-5 items-center justify-center rounded-full',
                                        group.iconClass,
                                    )}
                                >
                                    <group.icon className="size-3" />
                                </span>
                                {group.label}
                            </span>
                            <Select
                                value={chosen ? String(chosen.id) : ''}
                                onValueChange={(id) => onChange(Number(id))}
                            >
                                <SelectTrigger
                                    aria-label={group.label}
                                    className={cn(
                                        'h-auto min-h-11 w-full py-2 text-left',
                                        chosen && group.activeClass,
                                    )}
                                >
                                    <SelectValue
                                        placeholder={group.placeholder}
                                    >
                                        {chosen && (
                                            <span className="flex flex-col leading-tight">
                                                <span className="font-medium">
                                                    {chosen.name}
                                                </span>
                                                <span className="text-muted-foreground text-xs">
                                                    {schedule(chosen)}
                                                </span>
                                            </span>
                                        )}
                                    </SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    {options.map((plan) => (
                                        <SelectItem
                                            key={plan.id}
                                            value={String(plan.id)}
                                            className={cn(
                                                'py-2',
                                                group.itemClass,
                                            )}
                                        >
                                            <span className="flex flex-col leading-tight">
                                                <span className="font-medium">
                                                    {plan.name}
                                                </span>
                                                <span className="text-muted-foreground text-xs">
                                                    {schedule(plan)}
                                                </span>
                                            </span>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    );
                })}
            </div>

            {selected && <PlanSummary plan={selected} />}

            <InputError message={error} />
        </div>
    );
}

/** The chosen plan's details. Add a row here to show more information. */
function PlanSummary({ plan }: { plan: Plan }) {
    const gold = plan.product_category === 'gold';
    const isEmi = plan.installment_count !== null;

    const rows: { label: string; value: ReactNode }[] = [
        {
            label: isEmi ? 'Monthly EMI' : 'Amount',
            value: isEmi
                ? `${rupees(plan.amount)} / month`
                : `${rupees(plan.amount)} one-time`,
        },
        ...(isEmi
            ? [
                  {
                      label: 'Duration',
                      value: `${plan.installment_count} months`,
                  },
                  {
                      label: 'Total',
                      value: rupees(
                          Number(plan.amount) * (plan.installment_count ?? 0),
                      ),
                  },
              ]
            : []),
        { label: 'You get', value: entitlement(plan) },
    ];

    return (
        <div
            className={cn(
                'rounded-lg border p-3',
                gold
                    ? 'border-amber-200 bg-amber-50/60 dark:border-amber-900 dark:bg-amber-950/30'
                    : 'border-indigo-200 bg-indigo-50/60 dark:border-indigo-900 dark:bg-indigo-950/30',
            )}
        >
            <div className="mb-2 flex items-center justify-between gap-2">
                <span className="text-sm font-semibold">{plan.name}</span>
                <span
                    className={cn(
                        'rounded-full px-2 py-0.5 text-[11px] font-semibold tracking-wide uppercase',
                        gold
                            ? 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200'
                            : 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
                    )}
                >
                    {gold ? 'Gold' : 'Silver'} · {isEmi ? 'EMI' : 'One-time'}
                </span>
            </div>
            <dl className="grid grid-cols-1 gap-x-4 gap-y-1.5 text-sm sm:grid-cols-2">
                {rows.map((row) => (
                    <div key={row.label} className="flex flex-col">
                        <dt className="text-muted-foreground text-xs">
                            {row.label}
                        </dt>
                        <dd className="font-medium">{row.value}</dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}

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

/**
 * T-214 (08-10-2026, client request) — each metal's dropdown sits in a panel in that metal's own colours (polished
 * silver / polished gold), so the two choices read as silver and gold at a glance. The summary card uses the same set.
 */
const METALS: {
    metal: Metal;
    label: string;
    placeholder: string;
    icon: LucideIcon;
    /** The panel around the label and dropdown. */
    panelClass: string;
    /** The round polished-metal icon. */
    iconClass: string;
    labelClass: string;
    triggerClass: string;
    activeClass: string;
    /** Highlighted/selected option — a light tint so both lines stay readable (the theme's teal accent hid the price line, 06-10-2026). */
    itemClass: string;
    summaryClass: string;
    badgeClass: string;
}[] = [
    {
        metal: 'silver',
        label: 'Silver plans',
        placeholder: 'Choose a silver plan',
        icon: Gem,
        panelClass:
            'border-slate-300 bg-linear-to-br from-white via-slate-100 to-zinc-200 dark:border-slate-700 dark:from-slate-900 dark:via-slate-900 dark:to-zinc-800',
        iconClass:
            'bg-linear-to-br from-white via-slate-300 to-slate-500 text-slate-800 shadow-sm ring-1 ring-slate-400/60 dark:from-slate-200 dark:via-slate-400 dark:to-slate-600 dark:text-slate-900',
        labelClass: 'text-slate-700 dark:text-slate-200',
        triggerClass:
            'border-slate-300 bg-white/90 dark:border-slate-600 dark:bg-slate-950/60',
        activeClass:
            'border-slate-500 ring-2 ring-slate-300/70 dark:border-slate-400 dark:ring-slate-600',
        itemClass:
            'focus:bg-slate-100 focus:text-slate-950 data-[state=checked]:bg-slate-100 dark:focus:bg-slate-800 dark:focus:text-slate-50 dark:data-[state=checked]:bg-slate-800',
        summaryClass:
            'border-slate-300 bg-linear-to-br from-white via-slate-50 to-zinc-100 dark:border-slate-700 dark:from-slate-900 dark:via-slate-900 dark:to-zinc-900',
        badgeClass:
            'bg-linear-to-r from-slate-200 via-white to-slate-300 text-slate-800 ring-1 ring-slate-400/50 dark:from-slate-500 dark:via-slate-300 dark:to-slate-500 dark:text-slate-900',
    },
    {
        metal: 'gold',
        label: 'Gold plans',
        placeholder: 'Choose a gold plan',
        icon: Crown,
        panelClass:
            'border-amber-300 bg-linear-to-br from-yellow-50 via-amber-50 to-amber-200/70 dark:border-amber-800 dark:from-amber-950 dark:via-amber-950 dark:to-yellow-900/60',
        iconClass:
            'bg-linear-to-br from-yellow-200 via-amber-400 to-amber-600 text-amber-950 shadow-sm ring-1 ring-amber-500/60',
        labelClass: 'text-amber-800 dark:text-amber-300',
        triggerClass:
            'border-amber-300 bg-white/90 dark:border-amber-800 dark:bg-amber-950/60',
        activeClass:
            'border-amber-500 ring-2 ring-amber-300/70 dark:border-amber-500 dark:ring-amber-800',
        itemClass:
            'focus:bg-amber-50 focus:text-amber-950 data-[state=checked]:bg-amber-50 dark:focus:bg-amber-950 dark:focus:text-amber-50 dark:data-[state=checked]:bg-amber-950',
        summaryClass:
            'border-amber-300 bg-linear-to-br from-yellow-50 via-amber-50 to-amber-100 dark:border-amber-800 dark:from-amber-950 dark:via-amber-950 dark:to-yellow-950',
        badgeClass:
            'bg-linear-to-r from-yellow-300 via-amber-200 to-amber-400 text-amber-950 ring-1 ring-amber-500/50',
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
                        <div
                            key={group.metal}
                            className={cn(
                                'grid gap-2 rounded-lg border p-3',
                                group.panelClass,
                            )}
                        >
                            <span
                                className={cn(
                                    'flex items-center gap-2 text-sm font-semibold',
                                    group.labelClass,
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex size-7 items-center justify-center rounded-full',
                                        group.iconClass,
                                    )}
                                >
                                    <group.icon className="size-4" />
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
                                        group.triggerClass,
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
    const colours =
        METALS.find((group) => group.metal === plan.product_category) ??
        METALS[0];

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
        <div className={cn('rounded-lg border p-3', colours.summaryClass)}>
            <div className="mb-2 flex items-center justify-between gap-2">
                <span
                    className={cn('text-sm font-semibold', colours.labelClass)}
                >
                    {plan.name}
                </span>
                <span
                    className={cn(
                        'rounded-full px-2 py-0.5 text-[11px] font-semibold tracking-wide uppercase',
                        colours.badgeClass,
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

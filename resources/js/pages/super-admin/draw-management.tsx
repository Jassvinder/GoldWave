import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Dices, Trophy, Users } from 'lucide-react';
import { FormEventHandler, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { FormSection, type FormSectionColor } from '@/components/form-section';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { StatStrip } from '@/components/stat-strip';
import { formatDate } from '@/lib/utils';
import { monthPrize, reconcile } from '@/routes/super-admin/draw-management';

type Execution = {
    id: number;
    cycle_month_no: number;
    winner_no: number;
    prize_name: string | null;
    prize_value: string | null;
    metal_type: string | null;
    status: string;
    winner_customer_id: string;
    upline_benefit_customer_id: string | null;
    upline_benefit_name: string | null;
    executed_at: string | null;
    reconciled_at: string | null;
    reconciled_by: string | null;
    correction_notes: string[];
};

type Group = {
    id: number;
    group_no: number;
    size: number;
    status: string;
    /** e.g. "September 2026" — the month the group's 20-month draw cycle starts. */
    cycle_started_month: string | null;
    created_on: string | null;
    first_customer_id: string | null;
    last_customer_id: string | null;
    winners: number;
    /** Members of this group who haven't won yet, so are still in its monthly draws. */
    eligible_remaining: number;
    draws_done: number;
    cycle_months: number;
    /** The coming draw's prize; null once all draws are done. */
    next_draw: {
        cycle_month_no: number;
        prize_name: string;
        prize_value: string;
        metal_type: string;
        winners_count: number;
        is_default: boolean;
    } | null;
    /** Prizes Super Admin set for this group's months still to come. */
    custom_prizes: {
        cycle_month_no: number;
        prize_name: string;
        prize_value: string;
        winners_count: number;
    }[];
    executions: Execution[];
};

const rupees = (value: string | null) =>
    `₹${Number(value ?? 0).toLocaleString('en-IN')}`;

type Pool = {
    group_size: number;
    grouped: number;
    waiting: number;
    needed_for_next_group: number;
    not_yet_eligible: number;
};

type Props = { groups: Group[]; pool: Pool };

/** One labelled figure inside a group card, with a short hint underneath. */
function GroupFact({
    label,
    value,
    hint,
}: {
    label: string;
    value: string | number;
    hint: string;
}) {
    return (
        <div className="bg-muted/40 flex flex-col gap-0.5 rounded-md p-3">
            <span className="text-muted-foreground text-xs">{label}</span>
            <span className="text-lg font-semibold tabular-nums">{value}</span>
            <span className="text-muted-foreground text-xs">{hint}</span>
        </div>
    );
}

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'outline'> = {
    scheduled: 'outline',
    executed: 'secondary',
    reconciled: 'default',
};

/** `reconciled` is what the DB stores; Super Admin sees it as "Verified". */
const STATUS_LABEL: Record<string, string> = {
    scheduled: 'Scheduled',
    executed: 'Awaiting verification',
    reconciled: 'Verified',
};

/**
 * Super Admin sets this group's own prize and number of winners for a month not drawn yet (30-09-2026).
 * Months without one use the Silver/Gold default from Draw Settings.
 */
function MonthPrizeForm({ group }: { group: Group }) {
    const firstOpenMonth = group.draws_done + 1;
    const { data, setData, post, processing, errors } = useForm({
        cycle_month_no: firstOpenMonth,
        prize_name: group.next_draw?.prize_name ?? '',
        prize_value: Number(group.next_draw?.prize_value ?? 0),
        winners_count: group.next_draw?.winners_count ?? 1,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(monthPrize.url(group.id), { preserveScroll: true });
    };

    const months = Array.from(
        { length: group.cycle_months - group.draws_done },
        (_, i) => firstOpenMonth + i,
    );

    return (
        <form
            onSubmit={submit}
            className="flex flex-col gap-3 rounded-md border p-3"
        >
            <div>
                <h3 className="text-sm font-medium">Set prize for a month</h3>
                <p className="text-muted-foreground text-xs">
                    For this group only — overrides the default prize from Draw
                    Settings. Months 1–15 are Silver, 16–20 Gold.
                </p>
            </div>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div className="grid gap-1.5">
                    <Label htmlFor={`month-${group.id}`}>Month</Label>
                    <select
                        id={`month-${group.id}`}
                        className="border-input bg-background h-9 rounded-md border px-2 text-sm"
                        value={data.cycle_month_no}
                        onChange={(e) =>
                            setData('cycle_month_no', Number(e.target.value))
                        }
                    >
                        {months.map((month) => (
                            <option key={month} value={month}>
                                Month {month} ({month <= 15 ? 'Silver' : 'Gold'}
                                )
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.cycle_month_no} />
                </div>
                <div className="col-span-2 grid gap-1.5 sm:col-span-1">
                    <Label htmlFor={`prize-${group.id}`}>Prize Item</Label>
                    <Input
                        id={`prize-${group.id}`}
                        value={data.prize_name}
                        onChange={(e) => setData('prize_name', e.target.value)}
                        required
                    />
                    <InputError message={errors.prize_name} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor={`value-${group.id}`}>Value (₹)</Label>
                    <Input
                        id={`value-${group.id}`}
                        type="number"
                        min={1}
                        step="0.01"
                        value={data.prize_value}
                        onChange={(e) =>
                            setData('prize_value', Number(e.target.value))
                        }
                        required
                    />
                    <InputError message={errors.prize_value} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor={`winners-${group.id}`}>Winners</Label>
                    <Input
                        id={`winners-${group.id}`}
                        type="number"
                        min={1}
                        max={50}
                        value={data.winners_count}
                        onChange={(e) =>
                            setData('winners_count', Number(e.target.value))
                        }
                        required
                    />
                    <InputError message={errors.winners_count} />
                </div>
            </div>
            <Button
                type="submit"
                size="sm"
                variant="secondary"
                disabled={processing}
                className="self-start"
            >
                Save Prize
            </Button>
        </form>
    );
}

const GROUP_COLOR: Record<string, FormSectionColor> = {
    forming: 'purple',
    active: 'blue',
    completed: 'green',
};

/** INSTRUCTIONS.md M13's Admin view / Admin Draw Management — groups, executions, and the manual reconciliation screen. */
export default function SuperAdminDrawManagement({ groups, pool }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const [noteByExecution, setNoteByExecution] = useState<
        Record<number, string>
    >({});

    const submitReconcile =
        (executionId: number): FormEventHandler =>
        (e) => {
            e.preventDefault();
            router.post(reconcile.url(executionId), {
                correction_note: noteByExecution[executionId] ?? '',
            });
        };

    const allExecutions = groups.flatMap((group) => group.executions);
    const stats = {
        scheduled: allExecutions.filter((e) => e.status === 'scheduled').length,
        executed: allExecutions.filter((e) => e.status === 'executed').length,
        reconciled: allExecutions.filter((e) => e.status === 'reconciled')
            .length,
    };

    return (
        <>
            <Head title="Draw Management" />

            <div className="flex w-full flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <StatStrip
                    icon={Dices}
                    label="Draw Groups"
                    value={groups.length}
                    counts={[
                        {
                            label: 'Scheduled',
                            value: stats.scheduled,
                            color: 'amber',
                        },
                        {
                            label: 'Awaiting verification',
                            value: stats.executed,
                            color: 'blue',
                        },
                        {
                            label: 'Verified',
                            value: stats.reconciled,
                            color: 'green',
                        },
                    ]}
                />

                <FormSection
                    icon={Users}
                    color="teal"
                    title="How members are grouped"
                    description={`Eligible members are grouped in Customer ID order, ${pool.group_size} per group. A new group is formed only when ${pool.group_size} eligible members are waiting.`}
                    contentClassName="grid grid-cols-1 gap-3 sm:grid-cols-3"
                >
                    <GroupFact
                        label="In draw groups"
                        value={pool.grouped}
                        hint={`Across ${groups.length} group${groups.length === 1 ? '' : 's'} below`}
                    />
                    <GroupFact
                        label="Waiting for the next group"
                        value={pool.waiting}
                        hint={`Eligible, not grouped yet — ${pool.needed_for_next_group} more needed to form Group #${groups.length + 1}`}
                    />
                    <GroupFact
                        label="Not yet eligible"
                        value={pool.not_yet_eligible}
                        hint="Active EMI members who haven't paid their plan's Draw EMIs yet"
                    />
                </FormSection>

                {groups.length === 0 && (
                    <FormSection
                        icon={Dices}
                        color="teal"
                        title="No draw groups yet"
                        description="Draw groups will appear here once generated."
                    >
                        <p className="text-muted-foreground text-sm">
                            No draw groups generated yet.
                        </p>
                    </FormSection>
                )}

                <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    {groups.map((group) => (
                        <FormSection
                            key={group.id}
                            icon={Trophy}
                            color={GROUP_COLOR[group.status] ?? 'teal'}
                            title={`Group #${group.group_no} · ${group.first_customer_id ?? '—'} to ${group.last_customer_id ?? '—'}`}
                            description={`${group.size} members · formed on ${formatDate(group.created_on)} · ${group.cycle_months}-month draw cycle from ${group.cycle_started_month ?? '—'}`}
                            action={
                                <Badge variant="secondary">
                                    {group.status}
                                </Badge>
                            }
                            contentClassName="flex flex-col gap-3"
                        >
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <GroupFact
                                    label="Still in the draw"
                                    value={`${group.eligible_remaining} of ${group.size}`}
                                    hint="Members who haven't won yet"
                                />
                                <GroupFact
                                    label="Winners so far"
                                    value={group.winners}
                                    hint="Each winner leaves the group's pool"
                                />
                                <GroupFact
                                    label="Draws held"
                                    value={`${group.draws_done} of ${group.cycle_months}`}
                                    hint={`${group.cycle_months - group.draws_done} monthly draws left`}
                                />
                            </div>

                            {group.next_draw && (
                                <div className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-dashed p-3 text-sm">
                                    <span>
                                        <span className="text-muted-foreground">
                                            Next draw · Month{' '}
                                            {group.next_draw.cycle_month_no}:
                                        </span>{' '}
                                        <span className="font-medium">
                                            {group.next_draw.prize_name} ·{' '}
                                            {rupees(
                                                group.next_draw.prize_value,
                                            )}
                                            {group.next_draw.winners_count >
                                                1 &&
                                                ` · ${group.next_draw.winners_count} winners`}
                                        </span>
                                    </span>
                                    <Badge variant="outline">
                                        {group.next_draw.is_default
                                            ? `Default ${group.next_draw.metal_type} prize`
                                            : 'Set for this group'}
                                    </Badge>
                                </div>
                            )}

                            {group.custom_prizes.length > 0 && (
                                <div className="text-muted-foreground text-xs">
                                    Prizes set for this group:{' '}
                                    {group.custom_prizes
                                        .map(
                                            (p) =>
                                                `Month ${p.cycle_month_no} — ${p.prize_name} ${rupees(p.prize_value)}${p.winners_count > 1 ? ` × ${p.winners_count} winners` : ''}`,
                                        )
                                        .join(' · ')}
                                </div>
                            )}

                            {group.next_draw && (
                                <MonthPrizeForm group={group} />
                            )}

                            <div>
                                <h3 className="text-sm font-medium">
                                    Draw results
                                </h3>
                                <p className="text-muted-foreground text-xs">
                                    After a draw, check the result and hand over
                                    the prize, then press{' '}
                                    <span className="font-medium">
                                        Mark as Verified
                                    </span>
                                    . The winner never changes — the optional
                                    note is kept permanently as a record.
                                </p>
                            </div>

                            {group.executions.length === 0 && (
                                <p className="text-muted-foreground text-sm">
                                    No draw held for this group yet — the first
                                    one runs on the 15th.
                                </p>
                            )}

                            {group.executions.map((execution) => (
                                <div
                                    key={execution.id}
                                    className="flex flex-col gap-2 rounded-md border p-3 text-sm"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <div className="font-medium">
                                                Month {execution.cycle_month_no}{' '}
                                                — Winner
                                                {execution.winner_no > 1 ||
                                                group.executions.some(
                                                    (other) =>
                                                        other.cycle_month_no ===
                                                            execution.cycle_month_no &&
                                                        other.winner_no > 1,
                                                )
                                                    ? ` ${execution.winner_no}`
                                                    : ''}
                                                : {execution.winner_customer_id}
                                            </div>
                                            {execution.prize_name && (
                                                <div>
                                                    Prize:{' '}
                                                    {execution.prize_name} ·{' '}
                                                    {rupees(
                                                        execution.prize_value,
                                                    )}
                                                </div>
                                            )}
                                            <div className="text-muted-foreground">
                                                Executed{' '}
                                                {formatDate(
                                                    execution.executed_at,
                                                )}
                                            </div>
                                            {execution.upline_benefit_customer_id && (
                                                // T-199 — the winner's Sponsor (10+ directs) gets the same prize; highlighted so it isn't missed.
                                                <div className="mt-1.5 flex flex-wrap items-center gap-2 rounded-md bg-amber-50 px-2 py-1.5 text-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                                    <Badge className="bg-amber-500 text-white hover:bg-amber-500">
                                                        Upline benefit
                                                    </Badge>
                                                    <span>
                                                        <span className="font-semibold">
                                                            {
                                                                execution.upline_benefit_customer_id
                                                            }
                                                        </span>
                                                        {execution.upline_benefit_name &&
                                                            ` (${execution.upline_benefit_name})`}{' '}
                                                        also gets
                                                        {execution.prize_name
                                                            ? ` ${execution.prize_name} · ${rupees(execution.prize_value)}`
                                                            : ' the same prize'}
                                                    </span>
                                                </div>
                                            )}
                                            {execution.reconciled_at && (
                                                <div className="text-muted-foreground">
                                                    Verified{' '}
                                                    {formatDate(
                                                        execution.reconciled_at,
                                                    )}{' '}
                                                    by {execution.reconciled_by}
                                                </div>
                                            )}
                                            {execution.correction_notes.map(
                                                (note, index) => (
                                                    <div
                                                        key={index}
                                                        className="text-muted-foreground italic"
                                                    >
                                                        "{note}"
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                        <Badge
                                            variant={
                                                STATUS_VARIANT[
                                                    execution.status
                                                ] ?? 'outline'
                                            }
                                            className="shrink-0"
                                        >
                                            {STATUS_LABEL[execution.status] ??
                                                execution.status}
                                        </Badge>
                                    </div>

                                    {execution.status === 'executed' && (
                                        <form
                                            onSubmit={submitReconcile(
                                                execution.id,
                                            )}
                                            className="flex items-center gap-2"
                                        >
                                            <Input
                                                type="text"
                                                placeholder="Note, e.g. prize handed over at store (optional)"
                                                className="flex-1"
                                                value={
                                                    noteByExecution[
                                                        execution.id
                                                    ] ?? ''
                                                }
                                                onChange={(e) =>
                                                    setNoteByExecution({
                                                        ...noteByExecution,
                                                        [execution.id]:
                                                            e.target.value,
                                                    })
                                                }
                                            />
                                            <Button size="sm" type="submit">
                                                Mark as Verified
                                            </Button>
                                        </form>
                                    )}
                                </div>
                            ))}
                        </FormSection>
                    ))}
                </div>
            </div>
        </>
    );
}

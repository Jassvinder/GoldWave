import { Head, router, usePage } from '@inertiajs/react';
import { Dices, Trophy } from 'lucide-react';
import { FormEventHandler, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { FormSection, type FormSectionColor } from '@/components/form-section';
import { Input } from '@/components/ui/input';
import { StatStrip } from '@/components/stat-strip';
import { formatDate } from '@/lib/utils';
import { reconcile } from '@/routes/super-admin/draw-management';

type Execution = {
    id: number;
    cycle_month_no: number;
    status: string;
    winner_customer_id: string;
    upline_benefit_customer_id: string | null;
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
    cycle_started_month: string | null;
    eligible_remaining: number;
    executions: Execution[];
};

type Props = { groups: Group[] };

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'outline'> = {
    scheduled: 'outline',
    executed: 'secondary',
    reconciled: 'default',
};

const GROUP_COLOR: Record<string, FormSectionColor> = {
    forming: 'purple',
    active: 'blue',
    completed: 'green',
};

/** INSTRUCTIONS.md M13's Admin view / Admin Draw Management — groups, executions, and the manual reconciliation screen. */
export default function SuperAdminDrawManagement({ groups }: Props) {
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
                            label: 'Executed',
                            value: stats.executed,
                            color: 'blue',
                        },
                        {
                            label: 'Reconciled',
                            value: stats.reconciled,
                            color: 'green',
                        },
                    ]}
                />

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
                            title={`Group #${group.group_no} · Size ${group.size}`}
                            description={`Started ${formatDate(group.cycle_started_month)} · Eligible remaining: ${group.eligible_remaining}`}
                            action={
                                <Badge variant="secondary">
                                    {group.status}
                                </Badge>
                            }
                            contentClassName="flex flex-col gap-3"
                        >
                            {group.executions.length === 0 && (
                                <p className="text-muted-foreground text-sm">
                                    No executions yet.
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
                                                — Winner:{' '}
                                                {execution.winner_customer_id}
                                            </div>
                                            <div className="text-muted-foreground">
                                                Executed{' '}
                                                {formatDate(
                                                    execution.executed_at,
                                                )}
                                                {execution.upline_benefit_customer_id && (
                                                    <>
                                                        {' '}
                                                        · Upline benefit:{' '}
                                                        {
                                                            execution.upline_benefit_customer_id
                                                        }
                                                    </>
                                                )}
                                            </div>
                                            {execution.reconciled_at && (
                                                <div className="text-muted-foreground">
                                                    Reconciled{' '}
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
                                            {execution.status}
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
                                                placeholder="Correction note (optional)"
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
                                                Reconcile
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

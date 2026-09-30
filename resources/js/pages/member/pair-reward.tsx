import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { formatDate } from '@/lib/utils';

type Milestone = {
    milestone_no: number;
    name: string;
    min_directs: number;
    left: number;
    right: number;
    used_left: number | null;
    used_right: number | null;
    reward_amount: string | null;
    achieved_on: string | null;
};

type PoolSide = {
    team: number;
    unused: number;
    consumed: number;
    awaiting: number;
    inactive: number;
    dummy: number;
};

type AwaitingPlan = {
    plan_name: string;
    required_emis: number | null;
    left: number;
    right: number;
};

type Props = {
    progress: {
        unused_left: number;
        unused_right: number;
        consumed_left: number;
        consumed_right: number;
        qualified_directs: number;
    };
    pool: { left: PoolSide; right: PoolSide; awaiting_by_plan: AwaitingPlan[] };
    milestones: Milestone[];
    next_milestone: Milestone | null;
};

type PoolRow = {
    key: string;
    label: string;
    hint: string;
    left: number;
    right: number;
    total?: boolean;
};

const milestoneColumns: DataTableColumn<Milestone>[] = [
    { key: 'milestone_no', header: '#' },
    {
        key: 'name',
        header: 'Milestone',
        render: (row) => <span className="font-medium">{row.name}</span>,
    },
    { key: 'left', header: 'Left' },
    { key: 'right', header: 'Right' },
    { key: 'min_directs', header: 'Directs Needed' },
    {
        key: 'used',
        header: 'Entries Used',
        render: (row) =>
            row.used_left !== null || row.used_right !== null ? (
                `${row.used_left ?? 0}L / ${row.used_right ?? 0}R`
            ) : (
                <span className="text-muted-foreground">—</span>
            ),
    },
    {
        key: 'reward_amount',
        header: 'Reward',
        render: (row) =>
            row.reward_amount !== null ? (
                <Badge>₹{row.reward_amount}</Badge>
            ) : (
                <span className="text-muted-foreground">—</span>
            ),
    },
    {
        key: 'achieved_on',
        header: 'Date',
        render: (row) => formatDate(row.achieved_on),
    },
];

const poolColumns: DataTableColumn<PoolRow>[] = [
    {
        key: 'label',
        header: 'Team members',
        render: (row) => (
            <div>
                <div className={row.total ? 'font-semibold' : 'font-medium'}>
                    {row.label}
                </div>
                <div className="text-muted-foreground text-xs">{row.hint}</div>
            </div>
        ),
    },
    {
        key: 'left',
        header: 'Left',
        render: (row) => (
            <span className={row.total ? 'font-semibold' : undefined}>
                {row.left}
            </span>
        ),
    },
    {
        key: 'right',
        header: 'Right',
        render: (row) => (
            <span className={row.total ? 'font-semibold' : undefined}>
                {row.right}
            </span>
        ),
    },
];

const awaitingColumns: DataTableColumn<AwaitingPlan>[] = [
    {
        key: 'plan_name',
        header: 'Plan',
        render: (row) => <span className="font-medium">{row.plan_name}</span>,
    },
    {
        key: 'required_emis',
        header: 'Counts after',
        render: (row) =>
            row.required_emis !== null
                ? `${row.required_emis} paid EMI${row.required_emis === 1 ? '' : 's'}`
                : '—',
    },
    { key: 'left', header: 'Left' },
    { key: 'right', header: 'Right' },
];

/**
 * INSTRUCTIONS.md M11 — progress toward the next milestone, where every team
 * member's entry currently sits (28-09-2026), and one milestone table
 * carrying each achieved milestone's entries used, reward and date
 * (DOMAIN_LOGIC.md §7).
 */
export default function PairReward({
    progress,
    pool,
    milestones,
    next_milestone,
}: Props) {
    const poolRows: PoolRow[] = [
        {
            key: 'consumed',
            label: 'Used in milestones',
            hint: 'Already consumed by an achieved milestone — never reused.',
            left: pool.left.consumed,
            right: pool.right.consumed,
        },
        {
            key: 'unused',
            label: 'Unused — counting toward the next milestone',
            hint: 'Eligible entries carried forward until a milestone consumes them.',
            left: pool.left.unused,
            right: pool.right.unused,
        },
        {
            key: 'awaiting',
            label: 'Not yet eligible — EMI qualification pending',
            hint: 'Active members on an EMI plan who have not yet paid the EMIs their plan needs (see below).',
            left: pool.left.awaiting,
            right: pool.right.awaiting,
        },
        ...(pool.left.inactive + pool.right.inactive > 0
            ? [
                  {
                      key: 'inactive',
                      label: 'Not active',
                      hint: 'Registered but not active (payment pending or cancelled) — no entry.',
                      left: pool.left.inactive,
                      right: pool.right.inactive,
                  },
              ]
            : []),
        ...(pool.left.dummy + pool.right.dummy > 0
            ? [
                  {
                      key: 'dummy',
                      label: 'Company placeholder positions',
                      hint: 'Unassigned dummy entries — they never create a pair entry.',
                      left: pool.left.dummy,
                      right: pool.right.dummy,
                  },
              ]
            : []),
        {
            key: 'team',
            label: 'Total team',
            hint: 'Everyone placed under you on each leg.',
            left: pool.left.team,
            right: pool.right.team,
            total: true,
        },
    ];

    return (
        <>
            <Head title="Pair/Reward" />

            <div className="flex w-full flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Pair/Reward Progress
                        </CardTitle>
                        <CardDescription>
                            Unused: {progress.unused_left}L /{' '}
                            {progress.unused_right}R · Consumed:{' '}
                            {progress.consumed_left}L /{' '}
                            {progress.consumed_right}R
                        </CardDescription>
                    </CardHeader>
                    {next_milestone && (
                        <CardContent>
                            <div className="rounded-md border p-3 text-sm">
                                Next milestone: {next_milestone.name} (#
                                {next_milestone.milestone_no}) —{' '}
                                {next_milestone.left}L / {next_milestone.right}R
                                <div className="mt-1">
                                    Needs {next_milestone.min_directs} qualified
                                    directs — you have{' '}
                                    <span
                                        className={
                                            progress.qualified_directs >=
                                            next_milestone.min_directs
                                                ? 'font-medium text-green-700 dark:text-green-400'
                                                : 'font-medium text-amber-600 dark:text-amber-400'
                                        }
                                    >
                                        {progress.qualified_directs}
                                    </span>
                                    <span className="text-muted-foreground text-xs">
                                        {' '}
                                        (an EMI direct counts once its Pair
                                        qualification EMIs are paid)
                                    </span>
                                </div>
                                <div className="text-muted-foreground mt-1 text-xs">
                                    Still needed:{' '}
                                    {Math.max(
                                        next_milestone.left -
                                            progress.unused_left,
                                        0,
                                    )}
                                    L /{' '}
                                    {Math.max(
                                        next_milestone.right -
                                            progress.unused_right,
                                        0,
                                    )}
                                    R more unused entries
                                </div>
                            </div>
                        </CardContent>
                    )}
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Where Your Team Is Counted</CardTitle>
                        <CardDescription>
                            Every member placed under you lands in exactly one
                            row — only eligible joinings create a pair entry,
                            and each entry is used by one milestone at most.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <DataTable
                            columns={poolColumns}
                            rows={poolRows}
                            rowKey={(row) => row.key}
                        />

                        {pool.awaiting_by_plan.length > 0 && (
                            <div className="flex flex-col gap-2">
                                <h3 className="text-sm font-medium">
                                    EMI qualification pending — by plan
                                </h3>
                                <DataTable
                                    columns={awaitingColumns}
                                    rows={pool.awaiting_by_plan}
                                    rowKey={(row) => row.plan_name}
                                />
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Milestones</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            columns={milestoneColumns}
                            rows={milestones}
                            rowKey={(row) => row.milestone_no}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

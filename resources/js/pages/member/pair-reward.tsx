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

type Milestone = {
    milestone_no: number;
    min_directs: number;
    left: number;
    right: number;
};

type Reward = {
    milestone_no: number;
    left_consumed_count: number;
    right_consumed_count: number;
    reward_amount: string;
    calculated_for_month: string | null;
};

type Props = {
    progress: {
        unused_left: number;
        unused_right: number;
        consumed_left: number;
        consumed_right: number;
    };
    milestones: Milestone[];
    next_milestone: Milestone | null;
    rewards: Reward[];
};

const milestoneColumns: DataTableColumn<Milestone>[] = [
    { key: 'milestone_no', header: '#' },
    { key: 'left', header: 'Left' },
    { key: 'right', header: 'Right' },
    { key: 'min_directs', header: 'Min Directs' },
];

const rewardColumns: DataTableColumn<Reward>[] = [
    {
        key: 'milestone_no',
        header: 'Milestone',
        render: (row) => (
            <span className="font-medium">#{row.milestone_no}</span>
        ),
    },
    {
        key: 'left_consumed_count',
        header: 'Consumed',
        render: (row) =>
            `${row.left_consumed_count}L / ${row.right_consumed_count}R`,
    },
    {
        key: 'calculated_for_month',
        header: 'Month',
        render: (row) => row.calculated_for_month ?? '—',
    },
    {
        key: 'reward_amount',
        header: 'Reward',
        render: (row) => <Badge>₹{row.reward_amount}</Badge>,
    },
];

/** INSTRUCTIONS.md M11 — progress toward the next milestone, milestone table, consumed/available business, reward history (DOMAIN_LOGIC.md §7). */
export default function PairReward({
    progress,
    milestones,
    next_milestone,
    rewards,
}: Props) {
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
                                Next milestone #{next_milestone.milestone_no}:{' '}
                                {next_milestone.left}L / {next_milestone.right}R
                                (min {next_milestone.min_directs} directs)
                            </div>
                        </CardContent>
                    )}
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

                <Card>
                    <CardHeader>
                        <CardTitle>Reward History</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            columns={rewardColumns}
                            rows={rewards}
                            rowKey={(row) => row.milestone_no}
                            emptyMessage="No rewards yet."
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

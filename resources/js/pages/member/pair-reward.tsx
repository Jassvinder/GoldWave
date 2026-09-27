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
    reward_amount: string | null;
    achieved_on: string | null;
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
    { key: 'min_directs', header: 'Min Directs' },
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

/** INSTRUCTIONS.md M11 — progress toward the next milestone, and one milestone table carrying each achieved milestone's reward and date (DOMAIN_LOGIC.md §7). */
export default function PairReward({
    progress,
    milestones,
    next_milestone,
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
                                Next milestone: {next_milestone.name} (#
                                {next_milestone.milestone_no}) —{' '}
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
            </div>
        </>
    );
}

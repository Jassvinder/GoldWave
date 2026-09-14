import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

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

            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
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
                        <div className="grid grid-cols-4 gap-2 text-sm font-medium">
                            <div>#</div>
                            <div>Left</div>
                            <div>Right</div>
                            <div>Min Directs</div>
                        </div>
                        {milestones.map((milestone) => (
                            <div
                                key={milestone.milestone_no}
                                className="grid grid-cols-4 gap-2 border-t py-2 text-sm"
                            >
                                <div>{milestone.milestone_no}</div>
                                <div>{milestone.left}</div>
                                <div>{milestone.right}</div>
                                <div>{milestone.min_directs}</div>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Reward History</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {rewards.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                No rewards yet.
                            </p>
                        )}
                        {rewards.map((reward, index) => (
                            <div
                                key={index}
                                className="flex items-center justify-between rounded-md border p-3"
                            >
                                <div>
                                    <div className="font-medium">
                                        Milestone #{reward.milestone_no}
                                    </div>
                                    <div className="text-muted-foreground text-sm">
                                        {reward.left_consumed_count}L /{' '}
                                        {reward.right_consumed_count}R ·{' '}
                                        {reward.calculated_for_month}
                                    </div>
                                </div>
                                <Badge>₹{reward.reward_amount}</Badge>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

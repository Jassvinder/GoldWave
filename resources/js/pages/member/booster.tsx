import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDate } from '@/lib/utils';

type Level = {
    level_no: number;
    min_directs: number;
    team_split_left: number;
    team_split_right: number;
    monthly_benefit: number;
    duration_months: number;
};

type Schedule = {
    month_no: number;
    scheduled_date: string;
    amount: string;
    status: 'pending' | 'paid';
};

type Qualification = {
    level_no: number;
    qualified_at: string | null;
    schedules: Schedule[];
};

type Props = {
    direct_count: number;
    team_size: { left: number; right: number };
    levels: Level[];
    qualifications: Qualification[];
};

/** INSTRUCTIONS.md M12 — direct/team counts, qualification per level, 6-month benefit schedule (DOMAIN_LOGIC.md §9). */
export default function Booster({
    direct_count,
    team_size,
    levels,
    qualifications,
}: Props) {
    const qualifiedLevels = new Map(qualifications.map((q) => [q.level_no, q]));

    return (
        <>
            <Head title="Income Booster" />

            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Income Booster
                        </CardTitle>
                        <CardDescription>
                            {direct_count} directs · Team size {team_size.left}L
                            / {team_size.right}R
                        </CardDescription>
                    </CardHeader>
                </Card>

                {levels.map((level) => {
                    const qualification = qualifiedLevels.get(level.level_no);

                    return (
                        <Card key={level.level_no}>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <div>
                                    <CardTitle>
                                        Level {level.level_no}
                                    </CardTitle>
                                    <CardDescription>
                                        Min {level.min_directs} directs ·{' '}
                                        {level.team_split_left}L /{' '}
                                        {level.team_split_right}R · ₹
                                        {level.monthly_benefit}/month ×{' '}
                                        {level.duration_months}
                                    </CardDescription>
                                </div>
                                <Badge
                                    variant={
                                        qualification ? 'default' : 'secondary'
                                    }
                                >
                                    {qualification
                                        ? `Qualified ${formatDate(qualification.qualified_at)}`
                                        : 'Not qualified'}
                                </Badge>
                            </CardHeader>
                            {qualification && (
                                <CardContent className="flex flex-col gap-2">
                                    {qualification.schedules.map((schedule) => (
                                        <div
                                            key={schedule.month_no}
                                            className="flex items-center justify-between rounded-md border p-2 text-sm"
                                        >
                                            <div>
                                                Month {schedule.month_no} ·{' '}
                                                {formatDate(schedule.scheduled_date)}
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <span>₹{schedule.amount}</span>
                                                <Badge
                                                    variant={
                                                        schedule.status ===
                                                        'paid'
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {schedule.status}
                                                </Badge>
                                            </div>
                                        </div>
                                    ))}
                                </CardContent>
                            )}
                        </Card>
                    );
                })}
            </div>
        </>
    );
}

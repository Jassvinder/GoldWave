import { Users } from 'lucide-react';
import { StatStrip } from '@/components/stat-strip';

export type TeamCounts = {
    direct: number;
    left: number;
    right: number;
    total: number;
};

/**
 * T-194 — the member's team at a glance (Direct Members · Total Team · Left · Right), shown at the top of Directs
 * View and Tree View. The same numbers feed the Dashboard's Total Team card (`MemberNetworkSummary::teamCounts`).
 */
export function TeamSummaryStrip({ team }: { team: TeamCounts }) {
    return (
        <StatStrip
            icon={Users}
            label="Total Team"
            value={team.total}
            counts={[
                { label: 'Direct Members', value: team.direct, color: 'purple' },
                { label: 'Left', value: team.left, color: 'blue' },
                { label: 'Right', value: team.right, color: 'green' },
            ]}
        />
    );
}

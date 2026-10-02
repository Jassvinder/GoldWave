import { Head } from '@inertiajs/react';
import { Gift } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { Input } from '@/components/ui/input';
import { formatDate } from '@/lib/utils';

type GroupMember = {
    customer_id: string | null;
    name: string | null;
    is_winner_removed: boolean;
};

type Execution = {
    id: number;
    cycle_month_no: number;
    winner_name: string | null;
    executed_at: string | null;
    winner_customer_id: string | null;
    is_own_win: boolean;
    prize_name: string | null;
    prize_value: string | null;
    /** T-199 — the winner's Sponsor (10+ directs) who received the same prize. */
    upline_benefit_customer_id: string | null;
    upline_benefit_name: string | null;
    is_own_upline_benefit: boolean;
};

type Group = {
    id: number;
    group_no: number;
    status: 'active' | 'completed';
    size: number;
    first_customer_id: string | null;
    last_customer_id: string | null;
    winners_count: number;
    draws_held: number;
    cycle_months: number;
    next_draw: {
        cycle_month_no: number;
        prize_name: string | null;
        prize_value: string | null;
    } | null;
    members: GroupMember[];
    executions: Execution[];
};

/** A prize this member received because one of their directs won (T-199). */
type UplineBenefit = {
    id: number;
    group_no: number;
    cycle_month_no: number;
    executed_at: string | null;
    winner_customer_id: string | null;
    winner_name: string | null;
    prize_name: string | null;
    prize_value: string | null;
};

type Props = { groups: Group[]; upline_benefits: UplineBenefit[] };

const rupees = (value: string | null) =>
    `₹${Number(value ?? 0).toLocaleString('en-IN')}`;

/**
 * INSTRUCTIONS.md M13 — the member's draw group (number, Customer ID range, draws held, next prize), who is still in
 * the draw vs. who has won (with prize), and (T-199) any prize received as a winner's Sponsor.
 */
export default function Draw({ groups, upline_benefits }: Props) {
    const [selectedGroupId, setSelectedGroupId] = useState(groups[0]?.id);
    const [search, setSearch] = useState('');

    const selectedGroup = groups.find((g) => g.id === selectedGroupId);

    const filteredMembers = useMemo(() => {
        if (!selectedGroup) {
            return [];
        }

        if (!search.trim()) {
            return selectedGroup.members;
        }

        const query = search.trim().toLowerCase();

        return selectedGroup.members.filter((m) =>
            m.customer_id?.toLowerCase().includes(query),
        );
    }, [selectedGroup, search]);

    return (
        <>
            <Head title="Monthly Draw" />

            <div className="flex w-full flex-col gap-6 p-4">
                {upline_benefits.length > 0 && (
                    <UplineBenefitsCard benefits={upline_benefits} />
                )}

                {groups.length === 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Monthly Draw</CardTitle>
                            <CardDescription>
                                You are not part of any draw group yet.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    selectedGroup && (
                        <>
                            <GroupHeader
                                group={selectedGroup}
                                groups={groups}
                                onSelect={setSelectedGroupId}
                            />

                            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>
                                            Still in the Draw (
                                            {selectedGroup.members.length})
                                        </CardTitle>
                                        <CardDescription>
                                            {selectedGroup.members.length} of{' '}
                                            {selectedGroup.size} members are
                                            still in the draw
                                            {selectedGroup.winners_count > 0
                                                ? ` — ${selectedGroup.winners_count} ${selectedGroup.winners_count === 1 ? 'has' : 'have'} already won and left the draw`
                                                : ''}
                                            . Each month's winners are picked
                                            from this list.
                                        </CardDescription>
                                        <Input
                                            placeholder="Search by Customer ID"
                                            value={search}
                                            onChange={(e) =>
                                                setSearch(e.target.value)
                                            }
                                            className="mt-2 max-w-xs"
                                        />
                                    </CardHeader>
                                    <CardContent>
                                        <DataTable
                                            columns={memberColumns}
                                            rows={filteredMembers}
                                            rowKey={(row, index) =>
                                                row.customer_id ?? index
                                            }
                                            emptyMessage="No members found."
                                        />
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle>
                                            Winners (
                                            {selectedGroup.executions.length})
                                        </CardTitle>
                                        <CardDescription>
                                            Every winner of this group, month by
                                            month, with the prize won. A
                                            winner's Sponsor with 10+ direct
                                            members gets the same prize.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <DataTable
                                            columns={executionColumns}
                                            rows={selectedGroup.executions}
                                            rowKey={(row) => row.id}
                                            emptyMessage="No draw held for this group yet."
                                        />
                                    </CardContent>
                                </Card>
                            </div>
                        </>
                    )
                )}
            </div>
        </>
    );
}

/** T-200 — the group number, its Customer ID range, progress through the cycle and the next prize, at a glance. */
function GroupHeader({
    group,
    groups,
    onSelect,
}: {
    group: Group;
    groups: Group[];
    onSelect: (id: number) => void;
}) {
    return (
        <Card>
            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="flex flex-col gap-1.5">
                    <CardTitle className="text-2xl">
                        Monthly Draw · Group #{group.group_no}
                    </CardTitle>
                    <CardDescription>
                        {group.first_customer_id} to {group.last_customer_id} ·{' '}
                        {group.size} members · Draw {group.draws_held} of{' '}
                        {group.cycle_months} held
                    </CardDescription>
                    {group.next_draw && (
                        <p className="text-sm">
                            <span className="text-muted-foreground">
                                Next draw (Month{' '}
                                {group.next_draw.cycle_month_no}
                                ):
                            </span>{' '}
                            <span className="font-medium">
                                {group.next_draw.prize_name} ·{' '}
                                {rupees(group.next_draw.prize_value)}
                            </span>
                        </p>
                    )}
                </div>
                <div className="flex items-center gap-2">
                    <Badge variant="secondary" className="capitalize">
                        {group.status}
                    </Badge>
                    {groups.length > 1 && (
                        <select
                            aria-label="Choose draw group"
                            className="border-input bg-background rounded-md border px-2 py-1 text-sm"
                            value={group.id}
                            onChange={(e) => onSelect(Number(e.target.value))}
                        >
                            {groups.map((option) => (
                                <option key={option.id} value={option.id}>
                                    Group #{option.group_no}
                                </option>
                            ))}
                        </select>
                    )}
                </div>
            </CardHeader>
        </Card>
    );
}

/** T-199 — prizes this member received because one of their direct members won (Sponsor with 10+ directs). */
function UplineBenefitsCard({ benefits }: { benefits: UplineBenefit[] }) {
    return (
        <Card className="border-amber-300 dark:border-amber-800">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Gift className="size-5 text-amber-600" />
                    Upline benefits you received ({benefits.length})
                </CardTitle>
                <CardDescription>
                    You have 10+ direct members, so when one of your directs
                    wins a draw you get the same prize.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <DataTable
                    columns={uplineColumns}
                    rows={benefits}
                    rowKey={(row) => row.id}
                    emptyMessage="None yet."
                />
            </CardContent>
        </Card>
    );
}

const prizeCell = (name: string | null, value: string | null) =>
    name ? (
        <span className="flex flex-col">
            <span>{name}</span>
            <span className="text-muted-foreground text-xs">
                {rupees(value)}
            </span>
        </span>
    ) : (
        '—'
    );

const uplineColumns: DataTableColumn<UplineBenefit>[] = [
    {
        key: 'cycle_month_no',
        header: 'Draw',
        render: (row) => (
            <span className="flex flex-col">
                <span>
                    Group #{row.group_no} · Month {row.cycle_month_no}
                </span>
                <span className="text-muted-foreground text-xs">
                    {formatDate(row.executed_at)}
                </span>
            </span>
        ),
    },
    {
        key: 'winner_customer_id',
        header: 'Your direct who won',
        render: (row) => (
            <span className="flex flex-col">
                <span>{row.winner_customer_id}</span>
                <span className="text-muted-foreground text-xs">
                    {row.winner_name}
                </span>
            </span>
        ),
    },
    {
        key: 'prize_name',
        header: 'Prize',
        render: (row) => prizeCell(row.prize_name, row.prize_value),
    },
];

const memberColumns: DataTableColumn<GroupMember>[] = [
    { key: 'customer_id', header: 'Customer ID' },
    { key: 'name', header: 'Name' },
];

const executionColumns: DataTableColumn<Execution>[] = [
    {
        key: 'cycle_month_no',
        header: 'Month',
        render: (row) => (
            <span className="flex flex-col">
                <span>Month {row.cycle_month_no}</span>
                <span className="text-muted-foreground text-xs">
                    {formatDate(row.executed_at)}
                </span>
            </span>
        ),
    },
    {
        key: 'winner_customer_id',
        header: 'Winner',
        render: (row) => (
            <span className="flex flex-col gap-0.5">
                <span className="flex items-center gap-2">
                    {row.winner_customer_id}
                    {row.is_own_win && <Badge>You won!</Badge>}
                </span>
                <span className="text-muted-foreground text-xs">
                    {row.winner_name}
                </span>
                {row.upline_benefit_customer_id && (
                    <span className="text-xs text-amber-700 dark:text-amber-400">
                        {row.is_own_upline_benefit
                            ? 'You (their Sponsor) also received this prize'
                            : `Sponsor ${row.upline_benefit_customer_id} also received this prize`}
                    </span>
                )}
            </span>
        ),
    },
    {
        key: 'prize_name',
        header: 'Prize',
        render: (row) => prizeCell(row.prize_name, row.prize_value),
    },
];

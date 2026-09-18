import { Head } from '@inertiajs/react';
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
import { formatDate } from '@/lib/utils';
import { Input } from '@/components/ui/input';

type GroupMember = {
    customer_id: string | null;
    name: string | null;
    is_winner_removed: boolean;
};

type Execution = {
    cycle_month_no: number;
    executed_at: string | null;
    winner_customer_id: string | null;
    is_own_win: boolean;
};

type Group = {
    id: number;
    group_no: number;
    status: 'active' | 'completed';
    members: GroupMember[];
    executions: Execution[];
};

type Props = { groups: Group[] };

/** INSTRUCTIONS.md M13 — group list, member search, winner history, own draw status (DOMAIN_LOGIC.md §8). */
export default function Draw({ groups }: Props) {
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

    if (groups.length === 0) {
        return (
            <>
                <Head title="Monthly Draw" />
                <div className="mx-auto max-w-3xl p-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Monthly Draw</CardTitle>
                            <CardDescription>
                                You are not part of any draw group yet.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Monthly Draw" />

            <div className="flex w-full flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">Monthly Draw</CardTitle>
                        <CardDescription>
                            <select
                                className="border-input bg-background mt-2 rounded-md border px-2 py-1 text-sm"
                                value={selectedGroupId}
                                onChange={(e) =>
                                    setSelectedGroupId(Number(e.target.value))
                                }
                            >
                                {groups.map((group) => (
                                    <option key={group.id} value={group.id}>
                                        Group #{group.group_no} ({group.status})
                                    </option>
                                ))}
                            </select>
                        </CardDescription>
                    </CardHeader>
                </Card>

                {selectedGroup && (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle>Group Members</CardTitle>
                                <Input
                                    placeholder="Search by Customer ID"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
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
                                <CardTitle>Winner History</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <DataTable
                                    columns={executionColumns}
                                    rows={selectedGroup.executions}
                                    rowKey={(row) => row.cycle_month_no}
                                    emptyMessage="No draws executed yet for this group."
                                />
                            </CardContent>
                        </Card>
                    </>
                )}
            </div>
        </>
    );
}

const memberColumns: DataTableColumn<GroupMember>[] = [
    {
        key: 'customer_id',
        header: 'Customer ID',
        render: (row) => (
            <span>
                {row.customer_id}
                {row.is_winner_removed && (
                    <Badge variant="secondary" className="ml-2">
                        Won
                    </Badge>
                )}
            </span>
        ),
    },
    { key: 'name', header: 'Name' },
];

const executionColumns: DataTableColumn<Execution>[] = [
    {
        key: 'cycle_month_no',
        header: 'Month',
        render: (row) => `Month ${row.cycle_month_no}`,
    },
    {
        key: 'executed_at',
        header: 'Executed',
        render: (row) => formatDate(row.executed_at),
    },
    {
        key: 'winner_customer_id',
        header: 'Winner',
        render: (row) => (
            <span className="flex items-center gap-2">
                {row.winner_customer_id}
                {row.is_own_win && <Badge>You won!</Badge>}
            </span>
        ),
    },
];

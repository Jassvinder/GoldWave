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

            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
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
                            <CardContent className="flex flex-col gap-1">
                                <div className="grid grid-cols-3 gap-2 text-sm font-medium">
                                    <div>Sr. No.</div>
                                    <div>Customer ID</div>
                                    <div>Name</div>
                                </div>
                                {filteredMembers.map((member, index) => (
                                    <div
                                        key={member.customer_id ?? index}
                                        className="grid grid-cols-3 gap-2 border-t py-1 text-sm"
                                    >
                                        <div>{index + 1}</div>
                                        <div>
                                            {member.customer_id}
                                            {member.is_winner_removed && (
                                                <Badge
                                                    variant="secondary"
                                                    className="ml-2"
                                                >
                                                    Won
                                                </Badge>
                                            )}
                                        </div>
                                        <div>{member.name}</div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Winner History</CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-2">
                                {selectedGroup.executions.length === 0 && (
                                    <p className="text-muted-foreground text-sm">
                                        No draws executed yet for this group.
                                    </p>
                                )}
                                {selectedGroup.executions.map((execution) => (
                                    <div
                                        key={execution.cycle_month_no}
                                        className="flex items-center justify-between rounded-md border p-3 text-sm"
                                    >
                                        <div>
                                            Month {execution.cycle_month_no} ·{' '}
                                            {formatDate(execution.executed_at)}
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span>
                                                {execution.winner_customer_id}
                                            </span>
                                            {execution.is_own_win && (
                                                <Badge>You won!</Badge>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </>
                )}
            </div>
        </>
    );
}

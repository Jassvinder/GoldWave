import { Head, router } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { FormEventHandler, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataPagination } from '@/components/data-pagination';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { FilterBar } from '@/components/filter-bar';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { StatStrip } from '@/components/stat-strip';
import { formatDate } from '@/lib/utils';
import { exportMethod, index, show } from '@/routes/super-admin/members';
import type { Paginated } from '@/types';

type MemberRow = {
    id: number;
    customer_id: string;
    name: string | null;
    mobile: string | null;
    email: string | null;
    plan: string | null;
    sponsor_customer_id: string | null;
    status: string;
    activated_at: string | null;
    directs_count: number;
    placement_side: 'left' | 'right' | null;
    placement_parent_customer_id: string | null;
    team_left: number;
    team_right: number;
    team_total: number;
    is_store_owner: boolean;
};

type Props = {
    members: Paginated<MemberRow>;
    filters: {
        search?: string;
        plan?: string;
        status?: string;
        store_owner?: string;
    };
    plan_options: { code: string; name: string }[];
    status_options: string[];
    stats: { total: number; active: number; pending: number; inactive: number };
};

const statusVariant: Record<
    string,
    'default' | 'secondary' | 'outline' | 'destructive' | 'success'
> = {
    active: 'success',
    payment_pending: 'secondary',
    payment_confirmed: 'secondary',
    draft: 'outline',
    cancelled: 'destructive',
};

const ANY = '__any__';

/** INSTRUCTIONS.md's Admin Member Management — company-wide member list with search/filter/pagination/export. */
export default function SuperAdminMemberManagement({
    members,
    filters,
    plan_options,
    status_options,
    stats,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [plan, setPlan] = useState(filters.plan ?? ANY);
    const [status, setStatus] = useState(filters.status ?? ANY);
    const [storeOwner, setStoreOwner] = useState(
        filters.store_owner ? '1' : ANY,
    );

    const applyFilters = (overrides: Record<string, string | number> = {}) => {
        router.get(
            index.url(),
            {
                search,
                plan: plan === ANY ? undefined : plan,
                status: status === ANY ? undefined : status,
                store_owner: storeOwner === ANY ? undefined : 1,
                ...overrides,
            },
            { preserveState: true },
        );
    };

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        applyFilters();
    };

    const resetFilters = () => {
        setSearch('');
        setPlan(ANY);
        setStatus(ANY);
        setStoreOwner(ANY);
        router.get(index.url(), {}, { preserveState: true });
    };

    const columns: DataTableColumn<MemberRow>[] = [
        {
            key: 'customer_id',
            header: 'Customer ID',
            render: (row) => (
                <span className="font-medium">{row.customer_id}</span>
            ),
        },
        {
            key: 'name',
            header: 'Name',
            render: (row) => (
                <span>
                    {row.name ?? '—'}
                    {row.is_store_owner && (
                        <Badge variant="outline" className="ml-2">
                            Store Owner
                        </Badge>
                    )}
                </span>
            ),
        },
        { key: 'mobile', header: 'Mobile', render: (row) => row.mobile ?? '—' },
        {
            key: 'plan',
            header: 'Plan',
            render: (row) =>
                row.plan ? <Badge variant="outline">{row.plan}</Badge> : '—',
        },
        {
            key: 'sponsor_customer_id',
            header: 'Sponsor ID',
            render: (row) => row.sponsor_customer_id ?? '—',
        },
        {
            key: 'position',
            header: 'Position',
            render: (row) =>
                row.placement_side
                    ? `${row.placement_side === 'left' ? 'Left' : 'Right'} of ${row.placement_parent_customer_id ?? '—'}`
                    : 'Root',
        },
        {
            key: 'directs_count',
            header: 'Directs',
            render: (row) => row.directs_count,
        },
        {
            key: 'team',
            header: 'Team (L / R)',
            render: (row) => (
                <span title={`Total team ${row.team_total}`}>
                    {row.team_total}{' '}
                    <span className="text-muted-foreground">
                        ({row.team_left} / {row.team_right})
                    </span>
                </span>
            ),
        },
        {
            key: 'activated_at',
            header: 'Join Date',
            render: (row) => formatDate(row.activated_at),
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => (
                <Badge
                    variant={statusVariant[row.status] ?? 'outline'}
                    className="capitalize"
                >
                    {row.status.replace('_', ' ')}
                </Badge>
            ),
        },
    ];

    return (
        <>
            <Head title="Member Management" />

            <div className="flex w-full flex-col gap-4 p-4">
                <StatStrip
                    icon={Users}
                    label="Total Members"
                    value={stats.total}
                    counts={[
                        {
                            label: 'Active',
                            value: stats.active,
                            color: 'green',
                        },
                        {
                            label: 'Pending',
                            value: stats.pending,
                            color: 'amber',
                        },
                        {
                            label: 'Inactive',
                            value: stats.inactive,
                            color: 'red',
                        },
                    ]}
                    actions={
                        <Button variant="outline" size="sm" asChild>
                            <a href={exportMethod.url()}>Export CSV</a>
                        </Button>
                    }
                />

                <FilterBar
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Search by Customer ID, name, or mobile"
                    onSubmit={submitSearch}
                    onReset={resetFilters}
                >
                    <Select value={plan} onValueChange={setPlan}>
                        <SelectTrigger className="w-full sm:w-40">
                            <SelectValue placeholder="All Plans" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY}>All Plans</SelectItem>
                            {plan_options.map((option) => (
                                <SelectItem
                                    key={option.code}
                                    value={option.code}
                                >
                                    {option.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select value={status} onValueChange={setStatus}>
                        <SelectTrigger className="w-full sm:w-40">
                            <SelectValue placeholder="All Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY}>All Status</SelectItem>
                            {status_options.map((value) => (
                                <SelectItem
                                    key={value}
                                    value={value}
                                    className="capitalize"
                                >
                                    {value.replace('_', ' ')}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select value={storeOwner} onValueChange={setStoreOwner}>
                        <SelectTrigger className="w-full sm:w-44">
                            <SelectValue placeholder="All Members" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY}>All Members</SelectItem>
                            <SelectItem value="1">Store Owners only</SelectItem>
                        </SelectContent>
                    </Select>
                </FilterBar>

                <DataTable
                    columns={columns}
                    rows={members.data}
                    rowKey={(row) => row.id}
                    rowHref={(row) => show.url(row.id)}
                    emptyMessage="No members found."
                />

                <DataPagination
                    paginated={members}
                    onPageChange={(page) => applyFilters({ page })}
                />
            </div>
        </>
    );
}

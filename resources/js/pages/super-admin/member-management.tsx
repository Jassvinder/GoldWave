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
};

type Props = {
    members: Paginated<MemberRow>;
    filters: { search?: string; plan?: string; status?: string };
    plan_options: string[];
    status_options: string[];
    stats: { total: number; active: number; pending: number; inactive: number };
};

const statusVariant: Record<
    string,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    active: 'default',
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

    const applyFilters = (overrides: Record<string, string | number> = {}) => {
        router.get(
            index.url(),
            {
                search,
                plan: plan === ANY ? undefined : plan,
                status: status === ANY ? undefined : status,
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
        { key: 'name', header: 'Name', render: (row) => row.name ?? '—' },
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
                        <SelectTrigger className="w-full sm:w-36">
                            <SelectValue placeholder="All Plans" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY}>All Plans</SelectItem>
                            {plan_options.map((code) => (
                                <SelectItem key={code} value={code}>
                                    Plan {code}
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

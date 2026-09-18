import { Head, router } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import { FormEventHandler, useState } from 'react';
import { Badge } from '@/components/ui/badge';
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
import { audit } from '@/routes/super-admin/compensation';
import type { Paginated } from '@/types';

type Calculation = {
    id: number;
    type: string;
    beneficiary_customer_id: string | null;
    rule_version_no: number;
    level_no: number | null;
    rate_percent: string;
    amount: string;
    eligibility_status: string;
    skip_reason: string | null;
    created_at: string | null;
};

type Props = {
    calculations: Paginated<Calculation>;
    filters: {
        type?: string;
        customer_id?: string;
        eligibility_status?: string;
        rule_version_id?: string;
    };
};

const STATUS_VARIANT: Record<string, 'default' | 'secondary'> = {
    paid: 'default',
    skipped: 'secondary',
};

const TYPE_OPTIONS = ['level_income', 'purchase_repurchase'];
const STATUS_OPTIONS = ['paid', 'skipped'];
const ANY = '__any__';

/** INSTRUCTIONS.md's Admin Compensation Management calculation audit page — source event, beneficiary, rule version, level/rate, amount, eligibility status/reason, timestamp. No reversal/reference field exists (DOMAIN_LOGIC.md §21's "Still open" — no rule describes reversing a finalized calculation). */
export default function SuperAdminCompensationAudit({
    calculations,
    filters,
}: Props) {
    const [customerId, setCustomerId] = useState(filters.customer_id ?? '');
    const [type, setType] = useState(filters.type ?? ANY);
    const [status, setStatus] = useState(filters.eligibility_status ?? ANY);

    const applyFilters = (overrides: Record<string, string | number> = {}) => {
        router.get(
            audit.url(),
            {
                customer_id: customerId,
                type: type === ANY ? undefined : type,
                eligibility_status: status === ANY ? undefined : status,
                ...overrides,
            },
            { preserveState: true },
        );
    };

    const submitFilter: FormEventHandler = (e) => {
        e.preventDefault();
        applyFilters();
    };

    const resetFilters = () => {
        setCustomerId('');
        setType(ANY);
        setStatus(ANY);
        router.get(audit.url(), {}, { preserveState: true });
    };

    const columns: DataTableColumn<Calculation>[] = [
        {
            key: 'type',
            header: 'Type',
            render: (row) => (
                <span className="capitalize">
                    {row.type.replace(/_/g, ' ')}
                    {row.level_no ? ` — L${row.level_no}` : ''}
                </span>
            ),
        },
        {
            key: 'beneficiary_customer_id',
            header: 'Beneficiary',
            render: (row) => row.beneficiary_customer_id ?? '—',
        },
        {
            key: 'rule_version_no',
            header: 'Rule Version',
            render: (row) => `v${row.rule_version_no}`,
        },
        {
            key: 'rate_percent',
            header: 'Rate',
            render: (row) => `${row.rate_percent}%`,
        },
        { key: 'amount', header: 'Amount', render: (row) => `₹${row.amount}` },
        {
            key: 'eligibility_status',
            header: 'Status',
            render: (row) => (
                <div className="flex flex-col gap-0.5">
                    <Badge
                        variant={
                            STATUS_VARIANT[row.eligibility_status] ?? 'outline'
                        }
                    >
                        {row.eligibility_status}
                    </Badge>
                    {row.skip_reason && (
                        <span className="text-muted-foreground text-xs">
                            {row.skip_reason}
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: 'created_at',
            header: 'Date',
            render: (row) => formatDate(row.created_at),
        },
    ];

    return (
        <>
            <Head title="Compensation Calculation Audit" />

            <div className="flex w-full flex-col gap-4 p-4">
                <StatStrip
                    icon={ClipboardList}
                    label="Calculations"
                    value={calculations.total}
                    counts={[]}
                />

                <FilterBar
                    search={customerId}
                    onSearchChange={setCustomerId}
                    searchPlaceholder="Filter by beneficiary Customer ID"
                    onSubmit={submitFilter}
                    onReset={resetFilters}
                >
                    <Select value={type} onValueChange={setType}>
                        <SelectTrigger className="w-full sm:w-44">
                            <SelectValue placeholder="All Types" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY}>All Types</SelectItem>
                            {TYPE_OPTIONS.map((value) => (
                                <SelectItem
                                    key={value}
                                    value={value}
                                    className="capitalize"
                                >
                                    {value.replace(/_/g, ' ')}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select value={status} onValueChange={setStatus}>
                        <SelectTrigger className="w-full sm:w-36">
                            <SelectValue placeholder="All Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY}>All Status</SelectItem>
                            {STATUS_OPTIONS.map((value) => (
                                <SelectItem
                                    key={value}
                                    value={value}
                                    className="capitalize"
                                >
                                    {value}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FilterBar>

                <DataTable
                    columns={columns}
                    rows={calculations.data}
                    rowKey={(row) => row.id}
                    emptyMessage="No calculations found."
                />

                <DataPagination
                    paginated={calculations}
                    onPageChange={(page) => applyFilters({ page })}
                />
            </div>
        </>
    );
}

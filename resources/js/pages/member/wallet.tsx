import { Head, router } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import { DataPagination } from '@/components/data-pagination';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { FilterBar } from '@/components/filter-bar';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDate } from '@/lib/utils';
import { index } from '@/routes/member/wallet';
import type { Paginated } from '@/types';

type Entry = {
    id: number;
    entry_type: 'credit' | 'debit';
    category: string;
    amount: string;
    status: 'pending' | 'confirmed' | 'reversed';
    description: string | null;
    processed_at: string | null;
    created_at: string;
};

type Sort = { key: string; direction: 'asc' | 'desc' };

type Props = {
    wallet_balance: string;
    wallet_hold_amount: string;
    entries: Paginated<Entry>;
    filters: { search?: string };
    sort: Sort | null;
};

const columns: DataTableColumn<Entry>[] = [
    {
        key: 'category',
        header: 'Category',
        sortable: true,
        render: (row) => (
            <span className="font-medium capitalize">
                {row.category.replace(/_/g, ' ')}
            </span>
        ),
    },
    {
        key: 'description',
        header: 'Description',
        sortable: true,
        render: (row) => row.description ?? '—',
    },
    {
        key: 'processed_at',
        header: 'Date',
        sortable: true,
        render: (row) => formatDate(row.processed_at ?? row.created_at),
    },
    {
        key: 'amount',
        header: 'Amount',
        sortable: true,
        render: (row) => (
            <span
                className={
                    row.entry_type === 'credit'
                        ? 'text-green-600 dark:text-green-400'
                        : 'text-destructive'
                }
            >
                {row.entry_type === 'credit' ? '+' : '-'}₹{row.amount}
            </span>
        ),
    },
    {
        key: 'status',
        header: 'Status',
        sortable: true,
        render: (row) => (
            <Badge
                variant={row.status === 'confirmed' ? 'default' : 'secondary'}
            >
                {row.status}
            </Badge>
        ),
    },
];

/** INSTRUCTIONS.md M14 — balance + transaction ledger with search, column sorting and pagination (DOMAIN_LOGIC.md §12, T-126). */
export default function Wallet({
    wallet_balance,
    wallet_hold_amount,
    entries,
    filters,
    sort,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const visit = (params: Record<string, string | number | undefined>) => {
        router.get(index.url(), params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const currentParams = (): Record<string, string | undefined> => ({
        search: search || undefined,
        sort: sort?.key,
        direction: sort?.direction,
    });

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        visit({ ...currentParams(), page: undefined });
    };

    const resetSearch = () => {
        setSearch('');
        visit({});
    };

    // Clicking the active column flips its direction; a new column starts descending (newest / largest first).
    const changeSort = (key: string) => {
        visit({
            search: search || undefined,
            sort: key,
            direction:
                sort?.key === key && sort.direction === 'desc' ? 'asc' : 'desc',
        });
    };

    return (
        <>
            <Head title="Wallet" />

            <div className="flex w-full flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">Wallet</CardTitle>
                        <CardDescription>
                            Balance ₹{wallet_balance} · On hold ₹
                            {wallet_hold_amount}
                        </CardDescription>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Ledger</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <FilterBar
                            search={search}
                            onSearchChange={setSearch}
                            searchPlaceholder="Search category, description or status"
                            onSubmit={submitSearch}
                            onReset={resetSearch}
                        />

                        <DataTable
                            columns={columns}
                            rows={entries.data}
                            rowKey={(row) => row.id}
                            sort={sort ?? undefined}
                            onSortChange={changeSort}
                            emptyMessage="No wallet activity found."
                        />

                        <DataPagination
                            paginated={entries}
                            onPageChange={(page) =>
                                visit({ ...currentParams(), page })
                            }
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

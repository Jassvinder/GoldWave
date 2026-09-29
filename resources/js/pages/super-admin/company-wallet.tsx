import { Head, useForm, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { FormEventHandler } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/utils';
import { topUp } from '@/routes/super-admin/company-wallet';

type LedgerEntry = {
    id: number;
    entry_type: 'credit' | 'debit';
    category: string;
    amount: string;
    description: string;
    occurred_at: string;
};

type Props = { balance: string; entries: LedgerEntry[] };

const CATEGORY_LABEL: Record<string, string> = {
    assisted_registration: 'Assisted Registration',
    manual_topup: 'Manual Top-up',
};

const columns: DataTableColumn<LedgerEntry>[] = [
    {
        key: 'occurred_at',
        header: 'Date',
        render: (row) => formatDateTime(row.occurred_at),
    },
    {
        key: 'category',
        header: 'Source',
        render: (row) =>
            CATEGORY_LABEL[row.category] ?? row.category.replace(/_/g, ' '),
    },
    {
        key: 'description',
        header: 'Description',
        render: (row) => row.description,
    },
    {
        key: 'entry_type',
        header: 'Type',
        render: (row) => (
            <Badge
                variant={row.entry_type === 'credit' ? 'default' : 'secondary'}
                className="capitalize"
            >
                {row.entry_type}
            </Badge>
        ),
    },
    {
        key: 'amount',
        header: 'Amount',
        render: (row) =>
            `${row.entry_type === 'credit' ? '+' : '−'}₹${row.amount}`,
    },
];

/**
 * DOMAIN_LOGIC.md §12.2(b) — T-153. Credited whenever a Member/Store funds an
 * Assisted Registration from their own wallet. T-163 (28-09-2026) — Super
 * Admin can also top it up by hand; the balance can never go below zero.
 */
export default function SuperAdminCompanyWallet({ balance, entries }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const form = useForm({ amount: '', description: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(topUp.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <>
            <Head title="Company Wallet" />

            <div className="flex w-full flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-2xl">
                                Company Wallet
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-semibold">₹{balance}</p>
                            <p className="text-muted-foreground text-sm">
                                Credited when a Member or Store funds an
                                Assisted Registration from their own wallet, and
                                by Super Admin top-ups. The balance can never go
                                below zero — anything paid from this wallet is
                                blocked when the balance is not enough.
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Top Up</CardTitle>
                            <CardDescription>
                                Add money to the Company Wallet. It is recorded
                                in the ledger with your name.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={submit}
                                className="flex flex-col gap-4"
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="amount">Amount (₹)</Label>
                                    <Input
                                        id="amount"
                                        type="number"
                                        min="1"
                                        step="0.01"
                                        value={form.data.amount}
                                        onChange={(e) =>
                                            form.setData(
                                                'amount',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError message={form.errors.amount} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="description">
                                        Description (optional)
                                    </Label>
                                    <Input
                                        id="description"
                                        value={form.data.description}
                                        onChange={(e) =>
                                            form.setData(
                                                'description',
                                                e.target.value,
                                            )
                                        }
                                        maxLength={255}
                                        placeholder="e.g. Funds for draw prizes"
                                    />
                                    <InputError
                                        message={form.errors.description}
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                    className="self-start"
                                >
                                    <Plus />
                                    Top Up
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Ledger</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            columns={columns}
                            rows={entries}
                            rowKey={(row) => row.id}
                            emptyMessage="No Company Wallet activity yet."
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

import { Head, useForm, usePage } from '@inertiajs/react';
import { Wallet } from 'lucide-react';
import { FormEventHandler } from 'react';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { FormSection } from '@/components/form-section';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/utils';
import { topup } from '@/routes/super-admin/store-wallets';

type StoreInfo = {
    id: number;
    name: string;
    advance_amount: string;
    wallet_balance: string;
};

type Entry = {
    type: string;
    amount: string;
    reference: string;
    description: string | null;
    operator_name: string | null;
    occurred_at: string | null;
};

type Props = { store: StoreInfo; entries: Entry[] };

/** INSTRUCTIONS.md S10 — one store's Store Wallet detail, top-up, and ledger history. */
export default function SuperAdminStoreWalletDetail({ store, entries }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const { data, setData, post, processing, errors, reset } = useForm({
        amount: '',
        description: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(topup.url(store.id), { onSuccess: () => reset() });
    };

    return (
        <>
            <Head title={`${store.name} — Store Wallet`} />

            <div className="flex w-full flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <FormSection
                    icon={Wallet}
                    color="blue"
                    title={store.name}
                    contentClassName="flex flex-col gap-4"
                >
                    <div className="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <div className="text-muted-foreground text-xs">
                                Wallet Balance
                            </div>
                            <div className="font-medium">
                                ₹{store.wallet_balance}
                            </div>
                        </div>
                        <div>
                            <div className="text-muted-foreground text-xs">
                                Advance Amount
                            </div>
                            <div className="font-medium">
                                ₹{store.advance_amount}
                            </div>
                        </div>
                    </div>

                    <form
                        onSubmit={submit}
                        className="grid grid-cols-1 gap-3 border-t pt-4 sm:grid-cols-3"
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="amount">Top-up Amount</Label>
                            <Input
                                id="amount"
                                type="number"
                                step="0.01"
                                value={data.amount}
                                onChange={(e) =>
                                    setData('amount', e.target.value)
                                }
                            />
                            {errors.amount && (
                                <p className="text-destructive text-xs">
                                    {errors.amount}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="description">
                                Description (optional)
                            </Label>
                            <Input
                                id="description"
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                            />
                        </div>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="self-end"
                        >
                            Record Top-up
                        </Button>
                    </form>
                </FormSection>

                <FormSection icon={Wallet} color="amber" title="Wallet Ledger">
                    <DataTable
                        columns={entryColumns}
                        rows={entries}
                        rowKey={(row, index) => index}
                        emptyMessage="No wallet transactions yet."
                    />
                </FormSection>
            </div>
        </>
    );
}

const entryColumns: DataTableColumn<Entry>[] = [
    {
        key: 'type',
        header: 'Type',
        render: (row) => (
            <span className="font-medium capitalize">
                {row.type.replace(/_/g, ' ')}
            </span>
        ),
    },
    {
        key: 'description',
        header: 'Description',
        render: (row) => (
            <span className="text-muted-foreground">
                {row.description ?? 'Wallet transaction'} · Ref{' '}
                {row.reference.slice(0, 8)}
            </span>
        ),
    },
    {
        key: 'operator_name',
        header: 'By',
        render: (row) => row.operator_name ?? '—',
    },
    {
        key: 'occurred_at',
        header: 'Date',
        render: (row) => formatDate(row.occurred_at),
    },
    {
        key: 'amount',
        header: 'Amount',
        render: (row) => `₹${row.amount}`,
    },
];

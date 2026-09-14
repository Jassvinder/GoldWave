import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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

            <div className="mx-auto flex max-w-2xl flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            {store.name}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
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
                                        setData(
                                            'description',
                                            e.target.value,
                                        )
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
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Wallet Ledger</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {entries.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                No wallet transactions yet.
                            </p>
                        )}
                        {entries.map((entry, index) => (
                            <div
                                key={index}
                                className="flex items-start justify-between gap-3 rounded-md border p-3 text-sm"
                            >
                                <div className="min-w-0">
                                    <div className="font-medium capitalize">
                                        {entry.type.replace(/_/g, ' ')}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {entry.description ?? 'Wallet transaction'}
                                        {' · Ref '}
                                        {entry.reference.slice(0, 8)}
                                        {' · '}
                                        {entry.operator_name ?? '—'} ·{' '}
                                        {formatDate(entry.occurred_at)}
                                    </div>
                                </div>
                                <div className="shrink-0 font-medium whitespace-nowrap">
                                    ₹{entry.amount}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

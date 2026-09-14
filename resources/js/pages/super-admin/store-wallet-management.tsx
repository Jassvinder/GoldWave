import { Head, Link, usePage } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { show } from '@/routes/super-admin/store-wallets';

type StoreRow = {
    id: number;
    name: string;
    advance_amount: string;
    wallet_balance: string;
    wallet_status: string | null;
};

type Props = { stores: StoreRow[] };

/** INSTRUCTIONS.md S10 — view/credit Store Wallets, advance balance, wallet transaction history. */
export default function SuperAdminStoreWalletManagement({ stores }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;

    return (
        <>
            <Head title="Store Wallet Management" />

            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Store Wallets
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {stores.map((s) => (
                            <Link
                                key={s.id}
                                href={show.url(s.id)}
                                className="flex items-start justify-between gap-3 rounded-md border p-3 text-sm"
                            >
                                <div className="min-w-0">
                                    <div className="font-medium">
                                        {s.name}
                                    </div>
                                    <div className="text-muted-foreground">
                                        Advance: ₹{s.advance_amount}
                                    </div>
                                </div>
                                <div className="shrink-0 text-right whitespace-nowrap">
                                    <div className="font-medium">
                                        ₹{s.wallet_balance}
                                    </div>
                                    {s.wallet_status && (
                                        <Badge variant="secondary">
                                            {s.wallet_status}
                                        </Badge>
                                    )}
                                </div>
                            </Link>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

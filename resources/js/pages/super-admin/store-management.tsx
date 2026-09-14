import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { show as showStore, store } from '@/routes/super-admin/store-management';

type Store = {
    id: number;
    name: string;
    owner_name: string | null;
    status: string;
    jewellery_allocation_value: string;
    advance_amount: string;
    wallet_balance: string;
};

type UnassignedAdmin = { id: number; name: string; email: string };

type Props = { stores: Store[]; unassigned_admins: UnassignedAdmin[] };

/** INSTRUCTIONS.md S09 — create/manage stores, assign Store Owners, allocation/advance, status. */
export default function SuperAdminStoreManagement({
    stores,
    unassigned_admins,
}: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        owner_user_id: '',
        contact: '',
        location: '',
        jewellery_allocation_value: '0',
        advance_amount: '0',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(store.url(), { onSuccess: () => reset() });
    };

    return (
        <>
            <Head title="Store Management" />

            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Create Store
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submit}
                            className="grid grid-cols-1 gap-3 sm:grid-cols-2"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="name">Store Name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                />
                                {errors.name && (
                                    <p className="text-destructive text-xs">
                                        {errors.name}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label>Store Owner (optional)</Label>
                                <Select
                                    value={data.owner_user_id}
                                    onValueChange={(v) =>
                                        setData('owner_user_id', v)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="No owner yet" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {unassigned_admins.map((admin) => (
                                            <SelectItem
                                                key={admin.id}
                                                value={String(admin.id)}
                                            >
                                                {admin.name} ({admin.email})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="contact">
                                    Contact (optional)
                                </Label>
                                <Input
                                    id="contact"
                                    value={data.contact}
                                    onChange={(e) =>
                                        setData('contact', e.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="location">
                                    Location (optional)
                                </Label>
                                <Input
                                    id="location"
                                    value={data.location}
                                    onChange={(e) =>
                                        setData('location', e.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="jewellery_allocation_value">
                                    Jewellery Allocation Value
                                </Label>
                                <Input
                                    id="jewellery_allocation_value"
                                    type="number"
                                    step="0.01"
                                    value={data.jewellery_allocation_value}
                                    onChange={(e) =>
                                        setData(
                                            'jewellery_allocation_value',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="advance_amount">
                                    Advance Amount
                                </Label>
                                <Input
                                    id="advance_amount"
                                    type="number"
                                    step="0.01"
                                    value={data.advance_amount}
                                    onChange={(e) =>
                                        setData(
                                            'advance_amount',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="sm:col-span-2 sm:self-start"
                            >
                                Create Store
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Stores</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {stores.map((s) => (
                            <Link
                                key={s.id}
                                href={showStore.url(s.id)}
                                className="flex items-start justify-between gap-3 rounded-md border p-3 text-sm"
                            >
                                <div className="min-w-0">
                                    <div className="font-medium">
                                        {s.name}
                                    </div>
                                    <div className="text-muted-foreground">
                                        Owner: {s.owner_name ?? 'Unassigned'}{' '}
                                        · Wallet: ₹{s.wallet_balance}
                                    </div>
                                </div>
                                <Badge
                                    variant="secondary"
                                    className="shrink-0"
                                >
                                    {s.status}
                                </Badge>
                            </Link>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

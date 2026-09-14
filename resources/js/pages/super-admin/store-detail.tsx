import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate } from '@/lib/utils';
import {
    reassignOwner,
    status as updateStatus,
} from '@/routes/super-admin/store-management';

type Store = {
    id: number;
    name: string;
    owner_name: string | null;
    owner_user_id: number | null;
    contact: string | null;
    location: string | null;
    status: string;
    jewellery_allocation_value: string;
    advance_amount: string;
    wallet_balance: string;
};

type Activity = {
    action_type: string;
    operator_name: string | null;
    affected_customer_id: string | null;
    occurred_at: string | null;
};

type Sale = {
    id: number;
    transaction_type: string;
    customer_id: string | null;
    item_name: string;
    total_invoice_amount: string;
    status: string;
    created_at: string | null;
};

type UnassignedAdmin = { id: number; name: string; email: string };

type Props = {
    store: Store;
    activity: Activity[];
    recent_sales: Sale[];
    unassigned_admins: UnassignedAdmin[];
};

/** INSTRUCTIONS.md's Store detail page (Super Admin) — profile, owner, allocation/advance, wallet, activity, recent sales. */
export default function SuperAdminStoreDetail({
    store,
    activity,
    recent_sales,
    unassigned_admins,
}: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const reassignForm = useForm({ owner_user_id: '' });
    const statusForm = useForm({ status: store.status });

    const submitReassign: FormEventHandler = (e) => {
        e.preventDefault();
        reassignForm.post(reassignOwner.url(store.id));
    };

    const submitStatus: FormEventHandler = (e) => {
        e.preventDefault();
        statusForm.post(updateStatus.url(store.id));
    };

    return (
        <>
            <Head title={store.name} />

            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle className="text-2xl">
                            {store.name}
                        </CardTitle>
                        <Badge variant="secondary">{store.status}</Badge>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                            <div>
                                <div className="text-muted-foreground text-xs">
                                    Owner
                                </div>
                                <div className="font-medium">
                                    {store.owner_name ?? 'Unassigned'}
                                </div>
                            </div>
                            <div>
                                <div className="text-muted-foreground text-xs">
                                    Contact
                                </div>
                                <div className="font-medium">
                                    {store.contact ?? '—'}
                                </div>
                            </div>
                            <div>
                                <div className="text-muted-foreground text-xs">
                                    Location
                                </div>
                                <div className="font-medium">
                                    {store.location ?? '—'}
                                </div>
                            </div>
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
                                    Jewellery Allocation
                                </div>
                                <div className="font-medium">
                                    ₹{store.jewellery_allocation_value}
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

                        <div className="flex flex-col gap-3 border-t pt-4 sm:flex-row">
                            <form
                                onSubmit={submitReassign}
                                className="flex flex-1 items-end gap-2"
                            >
                                <div className="grid flex-1 gap-2">
                                    <span className="text-xs font-medium">
                                        Reassign Owner
                                    </span>
                                    <Select
                                        value={reassignForm.data.owner_user_id}
                                        onValueChange={(v) =>
                                            reassignForm.setData(
                                                'owner_user_id',
                                                v,
                                            )
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select an admin" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {unassigned_admins.map(
                                                (admin) => (
                                                    <SelectItem
                                                        key={admin.id}
                                                        value={String(
                                                            admin.id,
                                                        )}
                                                    >
                                                        {admin.name} (
                                                        {admin.email})
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button
                                    type="submit"
                                    disabled={reassignForm.processing}
                                >
                                    Reassign
                                </Button>
                            </form>

                            <form
                                onSubmit={submitStatus}
                                className="flex items-end gap-2"
                            >
                                <div className="grid gap-2">
                                    <span className="text-xs font-medium">
                                        Status
                                    </span>
                                    <Select
                                        value={statusForm.data.status}
                                        onValueChange={(v) =>
                                            statusForm.setData('status', v)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="active">
                                                Active
                                            </SelectItem>
                                            <SelectItem value="inactive">
                                                Inactive
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button
                                    type="submit"
                                    disabled={statusForm.processing}
                                >
                                    Update Status
                                </Button>
                            </form>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Recent Sales</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {recent_sales.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                No sales recorded yet.
                            </p>
                        )}
                        {recent_sales.map((sale) => (
                            <div
                                key={sale.id}
                                className="flex items-start justify-between gap-3 rounded-md border p-3 text-sm"
                            >
                                <div className="min-w-0">
                                    <div className="font-medium capitalize">
                                        {sale.transaction_type} —{' '}
                                        {sale.item_name}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {sale.customer_id ?? 'Walk-in'} ·{' '}
                                        {formatDate(sale.created_at)}
                                    </div>
                                </div>
                                <div className="shrink-0 text-right whitespace-nowrap font-medium">
                                    ₹{sale.total_invoice_amount}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Operational History</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {activity.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                No activity recorded yet.
                            </p>
                        )}
                        {activity.map((log, index) => (
                            <div
                                key={index}
                                className="flex items-center justify-between rounded-md border p-2 text-sm"
                            >
                                <span className="capitalize">
                                    {log.action_type.replace(/_/g, ' ')}
                                    {log.affected_customer_id
                                        ? ` — ${log.affected_customer_id}`
                                        : ''}
                                </span>
                                <span className="text-muted-foreground">
                                    {log.operator_name} ·{' '}
                                    {formatDate(log.occurred_at)}
                                </span>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

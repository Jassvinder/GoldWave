import { Head, useForm, usePage } from '@inertiajs/react';
import { Store as StoreIcon } from 'lucide-react';
import { FormEventHandler } from 'react';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { FormSection } from '@/components/form-section';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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

            <div className="flex w-full flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <FormSection
                    icon={StoreIcon}
                    color="blue"
                    title={store.name}
                    action={<Badge variant="secondary">{store.status}</Badge>}
                    contentClassName="flex flex-col gap-4"
                >
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
                                        reassignForm.setData('owner_user_id', v)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select an admin" />
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
                </FormSection>

                <FormSection
                    icon={StoreIcon}
                    color="amber"
                    title="Recent Sales"
                >
                    <DataTable
                        columns={saleColumns}
                        rows={recent_sales}
                        rowKey={(row) => row.id}
                        emptyMessage="No sales recorded yet."
                    />
                </FormSection>

                <FormSection
                    icon={StoreIcon}
                    color="teal"
                    title="Operational History"
                >
                    <DataTable
                        columns={activityColumns}
                        rows={activity}
                        rowKey={(row, index) => index}
                        emptyMessage="No activity recorded yet."
                    />
                </FormSection>
            </div>
        </>
    );
}

const saleColumns: DataTableColumn<Sale>[] = [
    {
        key: 'transaction_type',
        header: 'Transaction',
        render: (row) => (
            <span className="font-medium capitalize">
                {row.transaction_type} — {row.item_name}
            </span>
        ),
    },
    {
        key: 'customer_id',
        header: 'Customer',
        render: (row) => row.customer_id ?? 'Walk-in',
    },
    {
        key: 'created_at',
        header: 'Date',
        render: (row) => formatDate(row.created_at),
    },
    {
        key: 'total_invoice_amount',
        header: 'Amount',
        render: (row) => `₹${row.total_invoice_amount}`,
    },
];

const activityColumns: DataTableColumn<Activity>[] = [
    {
        key: 'action_type',
        header: 'Action',
        render: (row) => (
            <span className="capitalize">
                {row.action_type.replace(/_/g, ' ')}
                {row.affected_customer_id
                    ? ` — ${row.affected_customer_id}`
                    : ''}
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
];

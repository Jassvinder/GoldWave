import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Boxes, Store as StoreIcon } from 'lucide-react';
import { FormEventHandler, useState } from 'react';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { FormSection } from '@/components/form-section';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate } from '@/lib/utils';
import { index as adminUsersIndex } from '@/routes/super-admin/admin-users';
import {
    reassignOwner,
    resetPassword,
    status as updateStatus,
} from '@/routes/super-admin/store-management';
import { store as allocateInventory } from '@/routes/super-admin/store-management/inventory';

type Store = {
    id: number;
    name: string;
    store_code: string | null;
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

type InventoryItem = {
    id: number;
    item_name: string;
    metal: string;
    weight: string;
    quantity: number;
    price: string;
    description: string | null;
};

type Props = {
    store: Store;
    activity: Activity[];
    recent_sales: Sale[];
    unassigned_admins: UnassignedAdmin[];
    inventory: InventoryItem[];
};

/** INSTRUCTIONS.md's Store detail page (Super Admin) — profile, owner, allocation/advance, wallet, activity, recent sales. */
export default function SuperAdminStoreDetail({
    store,
    activity,
    recent_sales,
    unassigned_admins,
    inventory,
}: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const [confirmOpen, setConfirmOpen] = useState(false);
    const reassignForm = useForm({
        owner_user_id: '',
        current_password: '',
    });
    const statusForm = useForm({ status: store.status });
    const resetPasswordForm = useForm({ password_mode: 'auto', password: '' });

    const submitReassign: FormEventHandler = (e) => {
        e.preventDefault();
        reassignForm.post(reassignOwner.url(store.id), {
            onSuccess: () => {
                setConfirmOpen(false);
                reassignForm.reset();
            },
        });
    };

    const submitStatus: FormEventHandler = (e) => {
        e.preventDefault();
        statusForm.post(updateStatus.url(store.id));
    };

    const submitResetPassword: FormEventHandler = (e) => {
        e.preventDefault();
        resetPasswordForm.post(resetPassword.url(store.id));
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
                                Store ID
                            </div>
                            <div className="font-medium">
                                {store.store_code ?? '—'}
                            </div>
                        </div>
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

                    <div className="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:flex-wrap">
                        <div className="flex flex-1 items-end gap-2">
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
                                {unassigned_admins.length === 0 && (
                                    <p className="text-muted-foreground text-xs">
                                        No unassigned Admin —{' '}
                                        <Link
                                            href={adminUsersIndex()}
                                            className="text-primary underline underline-offset-2"
                                        >
                                            promote a member from Admin Users
                                        </Link>{' '}
                                        first.
                                    </p>
                                )}
                                {reassignForm.errors.owner_user_id && (
                                    <p className="text-destructive text-sm">
                                        {reassignForm.errors.owner_user_id}
                                    </p>
                                )}
                            </div>
                            <Dialog
                                open={confirmOpen}
                                onOpenChange={(next) => {
                                    setConfirmOpen(next);
                                    if (!next) {
                                        reassignForm.setData(
                                            'current_password',
                                            '',
                                        );
                                        reassignForm.clearErrors(
                                            'current_password',
                                        );
                                    }
                                }}
                            >
                                <DialogTrigger asChild>
                                    <Button
                                        type="button"
                                        disabled={
                                            reassignForm.data.owner_user_id ===
                                            ''
                                        }
                                    >
                                        Reassign
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="sm:max-w-sm">
                                    <DialogHeader>
                                        <DialogTitle>
                                            Confirm your password
                                        </DialogTitle>
                                        <DialogDescription>
                                            Reassigning a store owner is a
                                            protected action. Enter your own
                                            Super Admin password to continue. A
                                            new Store password will be generated
                                            for the new owner and shown once.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <form
                                        onSubmit={submitReassign}
                                        className="flex flex-col gap-4"
                                    >
                                        <div className="grid gap-2">
                                            <Label htmlFor="reassign_current_password">
                                                Your password
                                            </Label>
                                            <Input
                                                id="reassign_current_password"
                                                type="password"
                                                autoComplete="current-password"
                                                autoFocus
                                                value={
                                                    reassignForm.data
                                                        .current_password
                                                }
                                                onChange={(e) =>
                                                    reassignForm.setData(
                                                        'current_password',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            {reassignForm.errors
                                                .current_password && (
                                                <p className="text-destructive text-sm">
                                                    {
                                                        reassignForm.errors
                                                            .current_password
                                                    }
                                                </p>
                                            )}
                                        </div>
                                        <DialogFooter>
                                            <Button
                                                type="submit"
                                                disabled={
                                                    reassignForm.processing
                                                }
                                            >
                                                Confirm and Reassign
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        </div>

                        <form
                            onSubmit={submitResetPassword}
                            className="flex items-end gap-2"
                        >
                            <div className="grid gap-2">
                                <span className="text-xs font-medium">
                                    New / Reset Store Password
                                </span>
                                <Select
                                    value={resetPasswordForm.data.password_mode}
                                    onValueChange={(v) =>
                                        resetPasswordForm.setData(
                                            'password_mode',
                                            v,
                                        )
                                    }
                                >
                                    <SelectTrigger className="w-48">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="auto">
                                            Generate automatically
                                        </SelectItem>
                                        <SelectItem value="manual">
                                            Enter manually
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            {resetPasswordForm.data.password_mode ===
                                'manual' && (
                                <Input
                                    type="password"
                                    placeholder="Password"
                                    value={resetPasswordForm.data.password}
                                    onChange={(e) =>
                                        resetPasswordForm.setData(
                                            'password',
                                            e.target.value,
                                        )
                                    }
                                />
                            )}
                            <Button
                                type="submit"
                                disabled={resetPasswordForm.processing}
                                variant="outline"
                            >
                                Set Password
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
                    icon={Boxes}
                    color="purple"
                    title="Inventory"
                    description="Jewellery allocated to this store — item, metal, weight, quantity, price (DOMAIN_LOGIC.md §16.5)."
                    action={<AllocateInventoryDialog storeId={store.id} />}
                >
                    <DataTable
                        columns={inventoryColumns}
                        rows={inventory}
                        rowKey={(row) => row.id}
                        emptyMessage="No inventory allocated yet."
                    />
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

const inventoryColumns: DataTableColumn<InventoryItem>[] = [
    {
        key: 'item_name',
        header: 'Item',
        render: (row) => (
            <div>
                <div className="font-medium">{row.item_name}</div>
                {row.description && (
                    <div className="text-muted-foreground text-xs">
                        {row.description}
                    </div>
                )}
            </div>
        ),
    },
    {
        key: 'metal',
        header: 'Metal',
        render: (row) => <span className="capitalize">{row.metal}</span>,
    },
    {
        key: 'weight',
        header: 'Weight',
        render: (row) => `${row.weight}g`,
    },
    { key: 'quantity', header: 'Qty' },
    {
        key: 'price',
        header: 'Price / unit',
        render: (row) => `₹${row.price}`,
    },
];

type InventoryFormData = {
    item_name: string;
    metal: 'gold' | 'silver' | '';
    weight: string;
    quantity: string;
    price: string;
    description: string;
};

/**
 * "Add Inventory" — Super Admin's jewellery allocation to a store (new or existing), DOMAIN_LOGIC.md §16.5. Adding the
 * same item again (same name + metal + weight + price) increases that row's quantity rather than creating a duplicate.
 */
function AllocateInventoryDialog({ storeId }: { storeId: number }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm<InventoryFormData>({
            item_name: '',
            metal: '',
            weight: '',
            quantity: '1',
            price: '',
            description: '',
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(allocateInventory.url(storeId), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    reset();
                    clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button type="button" size="sm">
                    Add Inventory
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Add Inventory</DialogTitle>
                    <DialogDescription>
                        Adding the same item, metal, weight and price again
                        increases its quantity instead of creating a duplicate
                        row.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="inv_item_name">Item name</Label>
                        <Input
                            id="inv_item_name"
                            value={data.item_name}
                            onChange={(e) =>
                                setData('item_name', e.target.value)
                            }
                            placeholder="e.g. Gold Bangle"
                            autoFocus
                        />
                        {errors.item_name && (
                            <p className="text-destructive text-sm">
                                {errors.item_name}
                            </p>
                        )}
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="inv_metal">Metal</Label>
                            <Select
                                value={data.metal}
                                onValueChange={(v) =>
                                    setData(
                                        'metal',
                                        v as InventoryFormData['metal'],
                                    )
                                }
                            >
                                <SelectTrigger id="inv_metal">
                                    <SelectValue placeholder="Select metal" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="gold">Gold</SelectItem>
                                    <SelectItem value="silver">
                                        Silver
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            {errors.metal && (
                                <p className="text-destructive text-sm">
                                    {errors.metal}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="inv_weight">Weight (g/unit)</Label>
                            <Input
                                id="inv_weight"
                                type="number"
                                min="0.001"
                                step="0.001"
                                value={data.weight}
                                onChange={(e) =>
                                    setData('weight', e.target.value)
                                }
                            />
                            {errors.weight && (
                                <p className="text-destructive text-sm">
                                    {errors.weight}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="inv_quantity">Quantity</Label>
                            <Input
                                id="inv_quantity"
                                type="number"
                                min="1"
                                step="1"
                                value={data.quantity}
                                onChange={(e) =>
                                    setData('quantity', e.target.value)
                                }
                            />
                            {errors.quantity && (
                                <p className="text-destructive text-sm">
                                    {errors.quantity}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="inv_price">Price (₹/unit)</Label>
                            <Input
                                id="inv_price"
                                type="number"
                                min="0"
                                step="0.01"
                                value={data.price}
                                onChange={(e) =>
                                    setData('price', e.target.value)
                                }
                            />
                            {errors.price && (
                                <p className="text-destructive text-sm">
                                    {errors.price}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="inv_description">
                            Description{' '}
                            <span className="text-muted-foreground font-normal">
                                (optional)
                            </span>
                        </Label>
                        <Input
                            id="inv_description"
                            value={data.description}
                            maxLength={500}
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                            placeholder="e.g. 22K, hallmarked"
                        />
                        {errors.description && (
                            <p className="text-destructive text-sm">
                                {errors.description}
                            </p>
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Add
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

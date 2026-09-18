import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { EditAdminUserDialog } from '@/components/edit-admin-user-dialog';
import { Input } from '@/components/ui/input';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/utils';
import { findMember, store } from '@/routes/super-admin/admin-users';

type Admin = {
    id: number;
    name: string;
    email: string;
    mobile: string | null;
    customer_id: string | null;
    created_at: string | null;
    store: { id: number; name: string; status: string } | null;
};

type Props = { admins: Admin[] };

type MemberLookup =
    | { status: 'idle' }
    | { status: 'checking' }
    | { status: 'valid'; name: string | null; customerId: string }
    | { status: 'invalid'; message: string };

/**
 * INSTRUCTIONS.md S02 — promote an existing Member to Admin (Store Owner);
 * "access boundaries" = which store (if any) they own. Fixed 17-09-2026 —
 * every real Store Owner is already a company Member first, so this no
 * longer creates a standalone account; it looks one up by Customer ID and
 * promotes their existing login. Store assignment stays a separate step
 * (Store Management page).
 */
export default function SuperAdminUsers({ admins }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const [member, setMember] = useState<MemberLookup>({ status: 'idle' });
    const { data, setData, post, processing, errors, reset } = useForm({
        customer_id: '',
    });

    async function checkCustomerId(customerId: string) {
        if (!customerId) {
            setMember({ status: 'idle' });
            return;
        }

        setMember({ status: 'checking' });

        const xsrfToken = decodeURIComponent(
            document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
        );

        const response = await fetch(findMember.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken,
            },
            body: JSON.stringify({ customer_id: customerId }),
        });

        const body = await response.json();

        if (response.ok && body.valid) {
            setMember({
                status: 'valid',
                name: body.member_name,
                customerId: body.member_customer_id,
            });
        } else {
            setMember({
                status: 'invalid',
                message: body.message ?? 'Member not eligible.',
            });
        }
    }

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(store.url(), {
            onSuccess: () => {
                reset();
                setMember({ status: 'idle' });
            },
        });
    };

    return (
        <>
            <Head title="Admin Users" />

            <div className="flex w-full flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Promote a Member to Admin
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submit}
                            className="flex flex-col gap-3 sm:max-w-sm"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="customer_id">Customer ID</Label>
                                <Input
                                    id="customer_id"
                                    value={data.customer_id}
                                    onChange={(e) =>
                                        setData('customer_id', e.target.value)
                                    }
                                    onBlur={(e) =>
                                        checkCustomerId(e.target.value)
                                    }
                                    placeholder="GWL01"
                                />
                                {member.status === 'checking' && (
                                    <p className="text-muted-foreground text-sm">
                                        Checking…
                                    </p>
                                )}
                                {member.status === 'valid' && (
                                    <p className="text-sm text-green-600">
                                        Member:{' '}
                                        {member.name ?? member.customerId} (
                                        {member.customerId})
                                    </p>
                                )}
                                {member.status === 'invalid' && (
                                    <p className="text-destructive text-sm">
                                        {member.message}
                                    </p>
                                )}
                                <InputError message={errors.customer_id} />
                            </div>
                            <Button
                                type="submit"
                                disabled={
                                    processing || member.status !== 'valid'
                                }
                                className="self-start"
                            >
                                Promote to Admin
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Admin Users</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            columns={adminColumns}
                            rows={admins}
                            rowKey={(row) => row.id}
                            emptyMessage="No Admin users yet."
                            renderActions={(row) => (
                                <EditAdminUserDialog admin={row} />
                            )}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

const adminColumns: DataTableColumn<Admin>[] = [
    {
        key: 'name',
        header: 'Name',
        render: (row) => <span className="font-medium">{row.name}</span>,
    },
    {
        key: 'customer_id',
        header: 'Customer ID',
        render: (row) => row.customer_id ?? '—',
    },
    { key: 'email', header: 'Email' },
    {
        key: 'created_at',
        header: 'Created',
        render: (row) => formatDate(row.created_at),
    },
    {
        key: 'store',
        header: 'Store',
        render: (row) =>
            row.store ? (
                <div className="flex items-center gap-2">
                    <span>{row.store.name}</span>
                    <Badge variant="secondary" className="capitalize">
                        {row.store.status}
                    </Badge>
                </div>
            ) : (
                <Badge variant="outline">No store assigned</Badge>
            ),
    },
];

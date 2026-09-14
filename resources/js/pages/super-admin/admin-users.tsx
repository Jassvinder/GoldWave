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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/utils';
import { store } from '@/routes/super-admin/admin-users';

type Admin = {
    id: number;
    name: string;
    email: string;
    created_at: string | null;
    store: { id: number; name: string; status: string } | null;
};

type Props = { admins: Admin[] };

/** INSTRUCTIONS.md S02 — create/manage Admin users; "access boundaries" = which store (if any) they own. */
export default function SuperAdminUsers({ admins }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(store.url(), { onSuccess: () => reset() });
    };

    return (
        <>
            <Head title="Admin Users" />

            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Create Admin User
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submit}
                            className="grid grid-cols-1 gap-3 sm:grid-cols-3"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
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
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                />
                                {errors.email && (
                                    <p className="text-destructive text-xs">
                                        {errors.email}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(e) =>
                                        setData('password', e.target.value)
                                    }
                                />
                                {errors.password && (
                                    <p className="text-destructive text-xs">
                                        {errors.password}
                                    </p>
                                )}
                            </div>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="sm:col-span-3 sm:self-start"
                            >
                                Create Admin User
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Admin Users</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {admins.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                No Admin users yet.
                            </p>
                        )}
                        {admins.map((admin) => (
                            <div
                                key={admin.id}
                                className="flex items-start justify-between gap-3 rounded-md border p-3 text-sm"
                            >
                                <div className="min-w-0">
                                    <div className="font-medium">
                                        {admin.name}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {admin.email} · Created{' '}
                                        {formatDate(admin.created_at)}
                                    </div>
                                </div>
                                <div className="shrink-0 text-right">
                                    {admin.store ? (
                                        <>
                                            <div className="font-medium">
                                                {admin.store.name}
                                            </div>
                                            <Badge variant="secondary">
                                                {admin.store.status}
                                            </Badge>
                                        </>
                                    ) : (
                                        <Badge variant="outline">
                                            No store assigned
                                        </Badge>
                                    )}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

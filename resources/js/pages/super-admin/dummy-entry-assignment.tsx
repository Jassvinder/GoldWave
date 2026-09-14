import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
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
import { store } from '@/routes/super-admin/dummy-entry-assignment';

type Dummy = {
    id: number;
    customer_id: string;
    placeholder_name: string | null;
    placement_side: string | null;
    generated_at: string | null;
};

type Props = { unassigned: Dummy[] };

/** INSTRUCTIONS.md S05 — enter leader details into an available dummy entry. */
export default function SuperAdminDummyEntryAssignment({ unassigned }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const { data, setData, post, processing, errors, reset } = useForm({
        member_id: 0,
        name: '',
        email: '',
        mobile: '',
    });

    const select = (dummy: Dummy) => {
        setSelectedId(dummy.id);
        setData('member_id', dummy.id);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(store.url(), {
            onSuccess: () => {
                reset();
                setSelectedId(null);
            },
        });
    };

    return (
        <>
            <Head title="Dummy Entry Assignment" />

            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Unassigned Dummy Entries
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {unassigned.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                No unassigned dummy entries.
                            </p>
                        )}
                        {unassigned.map((dummy) => (
                            <button
                                key={dummy.id}
                                type="button"
                                onClick={() => select(dummy)}
                                className={`flex items-start justify-between gap-3 rounded-md border p-3 text-left text-sm ${selectedId === dummy.id ? 'border-primary' : ''}`}
                            >
                                <div className="min-w-0">
                                    <div className="font-medium">
                                        {dummy.customer_id}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {dummy.placeholder_name} · Placement:{' '}
                                        {dummy.placement_side ?? '—'}
                                    </div>
                                </div>
                                <div className="text-muted-foreground shrink-0 whitespace-nowrap">
                                    {formatDate(dummy.generated_at)}
                                </div>
                            </button>
                        ))}
                    </CardContent>
                </Card>

                {selectedId !== null && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Assign Leader</CardTitle>
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
                                    <Label htmlFor="mobile">
                                        Mobile (optional)
                                    </Label>
                                    <Input
                                        id="mobile"
                                        value={data.mobile}
                                        onChange={(e) =>
                                            setData('mobile', e.target.value)
                                        }
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="sm:col-span-3 sm:self-start"
                                >
                                    Assign Leader
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

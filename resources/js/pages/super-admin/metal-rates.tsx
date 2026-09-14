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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate } from '@/lib/utils';
import { store } from '@/routes/super-admin/metal-rates';

type Rate = {
    id: number;
    metal: string;
    rate_per_gram: string;
    effective_from: string | null;
};

type Props = { rates: Rate[] };

/** INSTRUCTIONS.md S07 — Gold & Silver rates together, with effective-date history. */
export default function SuperAdminMetalRates({ rates }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const { data, setData, post, processing, errors, reset } = useForm({
        metal: 'gold',
        rate_per_gram: '',
        effective_from: new Date().toISOString().slice(0, 10),
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(store.url(), { onSuccess: () => reset('rate_per_gram') });
    };

    return (
        <>
            <Head title="Gold & Silver Rate Settings" />

            <div className="mx-auto flex max-w-2xl flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Record New Rate
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submit}
                            className="grid grid-cols-1 gap-3 sm:grid-cols-3"
                        >
                            <div className="grid gap-2">
                                <Label>Metal</Label>
                                <Select
                                    value={data.metal}
                                    onValueChange={(v) => setData('metal', v)}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="gold">
                                            Gold
                                        </SelectItem>
                                        <SelectItem value="silver">
                                            Silver
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="rate_per_gram">
                                    Rate Per Gram
                                </Label>
                                <Input
                                    id="rate_per_gram"
                                    type="number"
                                    step="0.01"
                                    value={data.rate_per_gram}
                                    onChange={(e) =>
                                        setData(
                                            'rate_per_gram',
                                            e.target.value,
                                        )
                                    }
                                />
                                {errors.rate_per_gram && (
                                    <p className="text-destructive text-xs">
                                        {errors.rate_per_gram}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="effective_from">
                                    Effective From
                                </Label>
                                <Input
                                    id="effective_from"
                                    type="date"
                                    value={data.effective_from}
                                    onChange={(e) =>
                                        setData(
                                            'effective_from',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="sm:col-span-3 sm:self-start"
                            >
                                Save Rate
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Rate History</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {rates.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                No rates recorded yet.
                            </p>
                        )}
                        {rates.map((rate) => (
                            <div
                                key={rate.id}
                                className="flex items-start justify-between gap-3 rounded-md border p-3 text-sm"
                            >
                                <div className="min-w-0 font-medium capitalize">
                                    {rate.metal}
                                </div>
                                <div className="shrink-0 text-right whitespace-nowrap">
                                    <div className="font-medium">
                                        ₹{rate.rate_per_gram}/g
                                    </div>
                                    <div className="text-muted-foreground">
                                        {formatDate(rate.effective_from)}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

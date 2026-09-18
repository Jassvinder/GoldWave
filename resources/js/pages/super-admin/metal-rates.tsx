import { Head, useForm, usePage } from '@inertiajs/react';
import { Coins } from 'lucide-react';
import { FormEventHandler } from 'react';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { FormSection } from '@/components/form-section';
import { Button } from '@/components/ui/button';
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

                <FormSection icon={Coins} color="amber" title="Record New Rate">
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
                                    <SelectItem value="gold">Gold</SelectItem>
                                    <SelectItem value="silver">
                                        Silver
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="rate_per_gram">Rate Per Gram</Label>
                            <Input
                                id="rate_per_gram"
                                type="number"
                                step="0.01"
                                value={data.rate_per_gram}
                                onChange={(e) =>
                                    setData('rate_per_gram', e.target.value)
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
                                    setData('effective_from', e.target.value)
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
                </FormSection>

                <FormSection icon={Coins} color="blue" title="Rate History">
                    <DataTable
                        columns={rateColumns}
                        rows={rates}
                        rowKey={(row) => row.id}
                        emptyMessage="No rates recorded yet."
                    />
                </FormSection>
            </div>
        </>
    );
}

const rateColumns: DataTableColumn<Rate>[] = [
    {
        key: 'metal',
        header: 'Metal',
        render: (row) => <span className="capitalize">{row.metal}</span>,
    },
    {
        key: 'rate_per_gram',
        header: 'Rate',
        render: (row) => `₹${row.rate_per_gram}/g`,
    },
    {
        key: 'effective_from',
        header: 'Effective From',
        render: (row) => formatDate(row.effective_from),
    },
];

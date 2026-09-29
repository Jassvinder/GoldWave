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
import { formatDate, formatRatePer10g } from '@/lib/utils';
import { store } from '@/routes/super-admin/metal-rates';

type Rate = {
    id: number;
    metal: string;
    rate_per_gram: string;
    making_charge_percent: string;
    effective_from: string | null;
};

type Props = { rates: Rate[] };

/**
 * INSTRUCTIONS.md S07 — Gold & Silver rates together, with effective-date
 * history. T-165 (28-09-2026): the rate is entered and shown per 10 gm
 * (1 tola = 10 gm here), with a making-charges % per metal on the same row.
 */
export default function SuperAdminMetalRates({ rates }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const { data, setData, post, processing, errors, reset } = useForm({
        metal: 'gold',
        rate_per_10_grams: '',
        making_charge_percent: '',
        effective_from: new Date().toISOString().slice(0, 10),
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(store.url(), {
            onSuccess: () =>
                reset('rate_per_10_grams', 'making_charge_percent'),
        });
    };

    const latest = (metal: string) =>
        rates.find((rate) => rate.metal === metal);

    return (
        <>
            <Head title="Gold & Silver Rate Settings" />

            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <div className="grid gap-4 sm:grid-cols-2">
                    {['gold', 'silver'].map((metal) => {
                        const rate = latest(metal);

                        return (
                            <div
                                key={metal}
                                className="bg-card rounded-xl border p-4"
                            >
                                <div className="text-muted-foreground text-sm capitalize">
                                    Current {metal}
                                </div>
                                <div className="text-xl font-semibold">
                                    {rate
                                        ? formatRatePer10g(rate.rate_per_gram)
                                        : 'Not set'}
                                </div>
                                {rate && (
                                    <div className="text-muted-foreground text-xs">
                                        Making {rate.making_charge_percent}% ·
                                        since {formatDate(rate.effective_from)}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>

                <FormSection
                    icon={Coins}
                    color="amber"
                    title="Record New Rate"
                    description="Enter the rate per 10 gm (1 tola). Making charges are a % of the metal value and are set separately for Gold and Silver."
                >
                    <form
                        onSubmit={submit}
                        className="grid grid-cols-1 gap-3 sm:grid-cols-2"
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
                        <div className="grid gap-2">
                            <Label htmlFor="rate_per_10_grams">
                                Rate per 10 gm (₹)
                            </Label>
                            <Input
                                id="rate_per_10_grams"
                                type="number"
                                step="0.1"
                                min="0.1"
                                value={data.rate_per_10_grams}
                                onChange={(e) =>
                                    setData('rate_per_10_grams', e.target.value)
                                }
                                required
                            />
                            {errors.rate_per_10_grams && (
                                <p className="text-destructive text-xs">
                                    {errors.rate_per_10_grams}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="making_charge_percent">
                                Making Charges (%)
                            </Label>
                            <Input
                                id="making_charge_percent"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                value={data.making_charge_percent}
                                onChange={(e) =>
                                    setData(
                                        'making_charge_percent',
                                        e.target.value,
                                    )
                                }
                                required
                            />
                            {errors.making_charge_percent && (
                                <p className="text-destructive text-xs">
                                    {errors.making_charge_percent}
                                </p>
                            )}
                        </div>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="sm:col-span-2 sm:self-start"
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
        header: 'Rate per 10 gm',
        render: (row) => formatRatePer10g(row.rate_per_gram),
    },
    {
        key: 'making_charge_percent',
        header: 'Making',
        render: (row) => `${row.making_charge_percent}%`,
    },
    {
        key: 'effective_from',
        header: 'Effective From',
        render: (row) => formatDate(row.effective_from),
    },
];

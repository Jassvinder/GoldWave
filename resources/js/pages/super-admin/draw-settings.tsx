import { Head, useForm, usePage } from '@inertiajs/react';
import { Gift, Settings2 } from 'lucide-react';
import { FormEventHandler } from 'react';
import { FormSection } from '@/components/form-section';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/super-admin/draw-settings';

type Props = {
    draw_group_size: number;
    draw_prize_silver_name: string;
    draw_prize_silver_value: number;
    draw_prize_gold_name: string;
    draw_prize_gold_value: number;
    draw_winners_per_month: number;
    silver_months: number;
};

/**
 * INSTRUCTIONS.md S06 — group size and the default monthly prizes (30-09-2026): Silver for months
 * 1–silver_months and Gold after that, applied to every group's draw that has no prize of its own.
 */
export default function SuperAdminDrawSettings(props: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const { data, setData, post, processing, errors } = useForm({
        draw_group_size: props.draw_group_size,
        draw_prize_silver_name: props.draw_prize_silver_name,
        draw_prize_silver_value: props.draw_prize_silver_value,
        draw_prize_gold_name: props.draw_prize_gold_name,
        draw_prize_gold_value: props.draw_prize_gold_value,
        draw_winners_per_month: props.draw_winners_per_month,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(update.url());
    };

    const prizeFields = [
        {
            metal: 'silver' as const,
            title: `Silver prize — Months 1 to ${props.silver_months}`,
        },
        {
            metal: 'gold' as const,
            title: `Gold prize — Months ${props.silver_months + 1} to 20`,
        },
    ];

    return (
        <>
            <Head title="Draw Master Settings" />

            <form
                onSubmit={submit}
                className="mx-auto flex max-w-2xl flex-col gap-6 p-4"
            >
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <FormSection
                    icon={Settings2}
                    color="purple"
                    title="Draw Groups"
                    description="Eligible members are grouped in Customer ID order, this many per group."
                >
                    <div className="grid max-w-xs gap-2">
                        <Label htmlFor="draw_group_size">Group Size</Label>
                        <Input
                            id="draw_group_size"
                            type="number"
                            min={1}
                            value={data.draw_group_size}
                            onChange={(e) =>
                                setData(
                                    'draw_group_size',
                                    Number(e.target.value),
                                )
                            }
                        />
                        <InputError message={errors.draw_group_size} />
                    </div>
                </FormSection>

                <FormSection
                    icon={Gift}
                    color="amber"
                    title="Monthly Prizes"
                    description="Every group's monthly draw carries this prize. A past draw keeps the prize it was held for, even if you change it here later."
                    contentClassName="flex flex-col gap-5"
                >
                    {prizeFields.map(({ metal, title }) => {
                        const nameKey = `draw_prize_${metal}_name` as const;
                        const valueKey = `draw_prize_${metal}_value` as const;

                        return (
                            <fieldset
                                key={metal}
                                className="flex flex-col gap-3"
                            >
                                <legend className="mb-2 text-sm font-medium">
                                    {title}
                                </legend>
                                <div className="grid gap-3 sm:grid-cols-[2fr_1fr]">
                                    <div className="grid gap-2">
                                        <Label htmlFor={nameKey}>
                                            Prize Item
                                        </Label>
                                        <Input
                                            id={nameKey}
                                            value={data[nameKey]}
                                            onChange={(e) =>
                                                setData(nameKey, e.target.value)
                                            }
                                            required
                                        />
                                        <InputError message={errors[nameKey]} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor={valueKey}>
                                            Prize Value (₹)
                                        </Label>
                                        <Input
                                            id={valueKey}
                                            type="number"
                                            min={1}
                                            step="0.01"
                                            value={data[valueKey]}
                                            onChange={(e) =>
                                                setData(
                                                    valueKey,
                                                    Number(e.target.value),
                                                )
                                            }
                                            required
                                        />
                                        <InputError
                                            message={errors[valueKey]}
                                        />
                                    </div>
                                </div>
                            </fieldset>
                        );
                    })}

                    <div className="grid max-w-xs gap-2">
                        <Label htmlFor="draw_winners_per_month">
                            Winners per month
                        </Label>
                        <Input
                            id="draw_winners_per_month"
                            type="number"
                            min={1}
                            max={50}
                            value={data.draw_winners_per_month}
                            onChange={(e) =>
                                setData(
                                    'draw_winners_per_month',
                                    Number(e.target.value),
                                )
                            }
                            required
                        />
                        <p className="text-muted-foreground text-xs">
                            How many members win in each group's monthly draw.
                            Each winner gets the prize above.
                        </p>
                        <InputError message={errors.draw_winners_per_month} />
                    </div>
                </FormSection>

                <Button
                    type="submit"
                    disabled={processing}
                    className="self-start"
                >
                    Save Settings
                </Button>
            </form>
        </>
    );
}

import { Head, useForm, usePage } from '@inertiajs/react';
import { Settings2 } from 'lucide-react';
import { FormEventHandler } from 'react';
import { FormSection } from '@/components/form-section';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/super-admin/draw-settings';

type Props = { draw_group_size: number };

/** INSTRUCTIONS.md S06 — group size; monthly prize name/value is configured per group when it's generated, not a global setting here. */
export default function SuperAdminDrawSettings({ draw_group_size }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const { data, setData, post, processing } = useForm({
        draw_group_size,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(update.url());
    };

    return (
        <>
            <Head title="Draw Master Settings" />

            <div className="mx-auto flex max-w-md flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <FormSection
                    icon={Settings2}
                    color="purple"
                    title="Draw Master Settings"
                >
                    <form onSubmit={submit} className="flex flex-col gap-3">
                        <div className="grid gap-2">
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
                        </div>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="self-start"
                        >
                            Save Settings
                        </Button>
                    </form>
                </FormSection>
            </div>
        </>
    );
}

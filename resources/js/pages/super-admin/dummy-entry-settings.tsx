import { Head, router, useForm, usePage } from '@inertiajs/react';
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
    generate as generateNow,
    update,
} from '@/routes/super-admin/dummy-entry-settings';

type Props = {
    enabled: boolean;
    daily_count: number;
    stats: { generated: number; unassigned: number; assigned: number };
};

/** INSTRUCTIONS.md S04 — daily count, enable/disable, generation controls. */
export default function SuperAdminDummyEntrySettings({
    enabled,
    daily_count,
    stats,
}: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const { data, setData, post, processing } = useForm({
        enabled,
        daily_count,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(update.url());
    };

    const generate = () => {
        router.post(generateNow.url());
    };

    return (
        <>
            <Head title="Daily Dummy Entry Settings" />

            <div className="mx-auto flex max-w-2xl flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Company Direct Generation
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div className="grid grid-cols-3 gap-3 text-sm">
                            <div>
                                <div className="text-muted-foreground text-xs">
                                    Generated
                                </div>
                                <div className="font-medium">
                                    {stats.generated}
                                </div>
                            </div>
                            <div>
                                <div className="text-muted-foreground text-xs">
                                    Unassigned
                                </div>
                                <div className="font-medium">
                                    {stats.unassigned}
                                </div>
                            </div>
                            <div>
                                <div className="text-muted-foreground text-xs">
                                    Assigned
                                </div>
                                <div className="font-medium">
                                    {stats.assigned}
                                </div>
                            </div>
                        </div>

                        <form onSubmit={submit} className="flex flex-col gap-3">
                            <div className="flex items-center gap-2">
                                <input
                                    id="enabled"
                                    type="checkbox"
                                    checked={data.enabled}
                                    onChange={(e) =>
                                        setData('enabled', e.target.checked)
                                    }
                                />
                                <Label htmlFor="enabled">
                                    Enable daily generation
                                </Label>
                            </div>
                            <div className="grid max-w-xs gap-2">
                                <Label htmlFor="daily_count">
                                    Daily Count
                                </Label>
                                <Input
                                    id="daily_count"
                                    type="number"
                                    min={0}
                                    value={data.daily_count}
                                    onChange={(e) =>
                                        setData(
                                            'daily_count',
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

                        <div>
                            <Button variant="outline" onClick={generate}>
                                Generate Now
                            </Button>
                            <p className="text-muted-foreground mt-1 text-xs">
                                Runs generation immediately, using the saved
                                settings above (the scheduled job also runs
                                this automatically every day).
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

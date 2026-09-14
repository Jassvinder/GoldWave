import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/utils';
import { store } from '@/routes/super-admin/rule-versions';

type Milestone = { milestone_no: number; min_directs: number; left: number; right: number };
type BoosterLevel = {
    level_no: number;
    min_directs: number;
    team_split_left: number;
    team_split_right: number;
    monthly_benefit: number;
    duration_months: number;
};

type Version = {
    version_no: number;
    effective_from: string | null;
    effective_to: string | null;
    is_active: boolean;
    published_by: string | null;
    published_at: string | null;
    notes: string | null;
};

type Props = {
    versions: Version[];
    current: {
        level_income_rates: Record<string, number>;
        pair_value_per_entry: number;
        pair_milestones: Milestone[];
        pair_qualification_emis: Record<string, number>;
        booster_levels: BoosterLevel[];
        purchase_repurchase_income_rates: Record<string, number>;
        store_profit_distribution_rates: Record<string, number>;
        item_buyback_percent: number;
        store_gst_percent: number;
    };
};

/** INSTRUCTIONS.md S03 (also Admin Compensation Management's config page) — level percentages, pair/booster/store rates, effective dates/versions. */
export default function SuperAdminRuleVersions({ versions, current }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const { data, setData, post, processing } = useForm({
        notes: '',
        level_income_rates: current.level_income_rates,
        pair_value_per_entry: current.pair_value_per_entry,
        pair_milestones: current.pair_milestones,
        pair_qualification_emis: current.pair_qualification_emis,
        booster_levels: current.booster_levels,
        purchase_repurchase_income_rates: current.purchase_repurchase_income_rates,
        store_profit_distribution_rates: current.store_profit_distribution_rates,
        item_buyback_percent: current.item_buyback_percent,
        store_gst_percent: current.store_gst_percent,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(store.url());
    };

    const setLevelRate = (level: string, value: number) => {
        setData('level_income_rates', {
            ...data.level_income_rates,
            [level]: value,
        });
    };

    const setPairQualificationEmi = (plan: string, value: number) => {
        setData('pair_qualification_emis', {
            ...data.pair_qualification_emis,
            [plan]: value,
        });
    };

    const setPurchaseRate = (level: string, value: number) => {
        setData('purchase_repurchase_income_rates', {
            ...data.purchase_repurchase_income_rates,
            [level]: value,
        });
    };

    const setStoreRate = (key: string, value: number) => {
        setData('store_profit_distribution_rates', {
            ...data.store_profit_distribution_rates,
            [key]: value,
        });
    };

    const setMilestone = (
        index: number,
        field: keyof Milestone,
        value: number,
    ) => {
        const next = [...data.pair_milestones];
        next[index] = { ...next[index], [field]: value };
        setData('pair_milestones', next);
    };

    const setBoosterLevel = (
        index: number,
        field: keyof BoosterLevel,
        value: number,
    ) => {
        const next = [...data.booster_levels];
        next[index] = { ...next[index], [field]: value };
        setData('booster_levels', next);
    };

    return (
        <>
            <Head title="Compensation Rule Versions" />

            <div className="mx-auto flex max-w-4xl flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <form onSubmit={submit} className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-2xl">
                                Level Income Rates (L1–L12)
                            </CardTitle>
                            <CardDescription>
                                Percentage paid at each Sponsor/Direct level.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid grid-cols-3 gap-3 sm:grid-cols-6">
                            {Object.entries(data.level_income_rates).map(
                                ([level, rate]) => (
                                    <div key={level} className="grid gap-1">
                                        <Label className="text-xs">
                                            L{level}
                                        </Label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            value={rate}
                                            onChange={(e) =>
                                                setLevelRate(
                                                    level,
                                                    Number(e.target.value),
                                                )
                                            }
                                        />
                                    </div>
                                ),
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Pair/Reward Milestones</CardTitle>
                            <CardDescription>
                                Pair value per entry: reward = (left + right)
                                × pair value.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            <div className="grid max-w-xs gap-1">
                                <Label className="text-xs">
                                    Pair Value Per Entry
                                </Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    value={data.pair_value_per_entry}
                                    onChange={(e) =>
                                        setData(
                                            'pair_value_per_entry',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </div>
                            {data.pair_milestones.map((milestone, index) => (
                                <div
                                    key={milestone.milestone_no}
                                    className="grid grid-cols-2 gap-2 rounded-md border p-2 text-sm sm:grid-cols-4"
                                >
                                    <div>Milestone #{milestone.milestone_no}</div>
                                    <div className="grid gap-1">
                                        <Label className="text-xs">
                                            Min Directs
                                        </Label>
                                        <Input
                                            type="number"
                                            value={milestone.min_directs}
                                            onChange={(e) =>
                                                setMilestone(
                                                    index,
                                                    'min_directs',
                                                    Number(e.target.value),
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label className="text-xs">Left</Label>
                                        <Input
                                            type="number"
                                            value={milestone.left}
                                            onChange={(e) =>
                                                setMilestone(
                                                    index,
                                                    'left',
                                                    Number(e.target.value),
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label className="text-xs">
                                            Right
                                        </Label>
                                        <Input
                                            type="number"
                                            value={milestone.right}
                                            onChange={(e) =>
                                                setMilestone(
                                                    index,
                                                    'right',
                                                    Number(e.target.value),
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Pair Qualification Requirements</CardTitle>
                            <CardDescription>
                                Minimum completed EMIs per plan before an EMI
                                joining fully qualifies for Pair/Reward.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            {Object.entries(
                                data.pair_qualification_emis,
                            ).map(([plan, count]) => (
                                <div key={plan} className="grid gap-1">
                                    <Label className="text-xs">
                                        Plan {plan}
                                    </Label>
                                    <Input
                                        type="number"
                                        value={count}
                                        onChange={(e) =>
                                            setPairQualificationEmi(
                                                plan,
                                                Number(e.target.value),
                                            )
                                        }
                                    />
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Income Booster Levels</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            {data.booster_levels.map((level, index) => (
                                <div
                                    key={level.level_no}
                                    className="grid grid-cols-2 gap-2 rounded-md border p-2 text-sm sm:grid-cols-6"
                                >
                                    <div>Level {level.level_no}</div>
                                    <div className="grid gap-1">
                                        <Label className="text-xs">
                                            Min Directs
                                        </Label>
                                        <Input
                                            type="number"
                                            value={level.min_directs}
                                            onChange={(e) =>
                                                setBoosterLevel(
                                                    index,
                                                    'min_directs',
                                                    Number(e.target.value),
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label className="text-xs">
                                            Team Left
                                        </Label>
                                        <Input
                                            type="number"
                                            value={level.team_split_left}
                                            onChange={(e) =>
                                                setBoosterLevel(
                                                    index,
                                                    'team_split_left',
                                                    Number(e.target.value),
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label className="text-xs">
                                            Team Right
                                        </Label>
                                        <Input
                                            type="number"
                                            value={level.team_split_right}
                                            onChange={(e) =>
                                                setBoosterLevel(
                                                    index,
                                                    'team_split_right',
                                                    Number(e.target.value),
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label className="text-xs">
                                            Monthly Benefit
                                        </Label>
                                        <Input
                                            type="number"
                                            value={level.monthly_benefit}
                                            onChange={(e) =>
                                                setBoosterLevel(
                                                    index,
                                                    'monthly_benefit',
                                                    Number(e.target.value),
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label className="text-xs">
                                            Duration (mo)
                                        </Label>
                                        <Input
                                            type="number"
                                            value={level.duration_months}
                                            onChange={(e) =>
                                                setBoosterLevel(
                                                    index,
                                                    'duration_months',
                                                    Number(e.target.value),
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>
                                Purchase/Repurchase Upline Rates
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-3 gap-3 sm:grid-cols-7">
                            {Object.entries(
                                data.purchase_repurchase_income_rates,
                            ).map(([level, rate]) => (
                                <div key={level} className="grid gap-1">
                                    <Label className="text-xs capitalize">
                                        {level === 'self' ? 'Self' : `L${level}`}
                                    </Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        value={rate}
                                        onChange={(e) =>
                                            setPurchaseRate(
                                                level,
                                                Number(e.target.value),
                                            )
                                        }
                                    />
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Store Profit Distribution Rates</CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            {Object.entries(
                                data.store_profit_distribution_rates,
                            ).map(([key, rate]) => (
                                <div key={key} className="grid gap-1">
                                    <Label className="text-xs capitalize">
                                        {key.replace(/_/g, ' ')}
                                    </Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        value={rate}
                                        onChange={(e) =>
                                            setStoreRate(
                                                key,
                                                Number(e.target.value),
                                            )
                                        }
                                    />
                                </div>
                            ))}
                            <div className="grid gap-1">
                                <Label className="text-xs">
                                    Item Buyback %
                                </Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    value={data.item_buyback_percent}
                                    onChange={(e) =>
                                        setData(
                                            'item_buyback_percent',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label className="text-xs">
                                    Store GST %
                                </Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    value={data.store_gst_percent}
                                    onChange={(e) =>
                                        setData(
                                            'store_gst_percent',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Publish New Version</CardTitle>
                            <CardDescription>
                                Publishing creates a brand-new rule version
                                effective today — the version above stays
                                intact for historical calculations.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="notes">Notes</Label>
                                <textarea
                                    id="notes"
                                    className="border-input bg-background rounded-md border px-3 py-2 text-sm"
                                    rows={3}
                                    value={data.notes}
                                    onChange={(e) =>
                                        setData('notes', e.target.value)
                                    }
                                />
                            </div>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="self-start"
                            >
                                Publish New Version
                            </Button>
                        </CardContent>
                    </Card>
                </form>

                <Card>
                    <CardHeader>
                        <CardTitle>Version History</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {versions.map((version) => (
                            <div
                                key={version.version_no}
                                className="flex items-start justify-between gap-3 rounded-md border p-3 text-sm"
                            >
                                <div className="min-w-0">
                                    <div className="font-medium">
                                        Version {version.version_no}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {formatDate(version.effective_from)}
                                        {' – '}
                                        {version.effective_to
                                            ? formatDate(version.effective_to)
                                            : 'now'}{' '}
                                        · Published by{' '}
                                        {version.published_by ?? '—'} on{' '}
                                        {formatDate(version.published_at)}
                                    </div>
                                    {version.notes && (
                                        <div className="text-muted-foreground">
                                            {version.notes}
                                        </div>
                                    )}
                                </div>
                                {version.is_active && (
                                    <Badge className="shrink-0">Active</Badge>
                                )}
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

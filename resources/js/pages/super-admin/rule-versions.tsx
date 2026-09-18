import { Head, useForm, usePage } from '@inertiajs/react';
import {
    Coins,
    FileText,
    History,
    ShoppingCart,
    Store as StoreIcon,
    Trophy,
    TrendingUp,
    Users,
} from 'lucide-react';
import { FormEventHandler } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { FormSection } from '@/components/form-section';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/utils';
import { VersionTimeline } from '@/components/version-timeline';
import { store } from '@/routes/super-admin/rule-versions';

type Milestone = {
    milestone_no: number;
    min_directs: number;
    left: number;
    right: number;
};
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
    changes: string[] | null;
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
    const activeVersion = versions.find((version) => version.is_active);
    const { data, setData, post, processing } = useForm({
        notes: '',
        level_income_rates: current.level_income_rates,
        pair_value_per_entry: current.pair_value_per_entry,
        pair_milestones: current.pair_milestones,
        pair_qualification_emis: current.pair_qualification_emis,
        booster_levels: current.booster_levels,
        purchase_repurchase_income_rates:
            current.purchase_repurchase_income_rates,
        store_profit_distribution_rates:
            current.store_profit_distribution_rates,
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
            <Head title="Compensation Settings" />

            <div className="flex w-full flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Compensation Settings
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            Configure income rates, milestones, and
                            qualification rules, and publish new versions.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Badge variant="outline" className="h-auto py-1.5">
                            <span className="mr-1 size-1.5 rounded-full bg-emerald-500" />
                            Current Version
                        </Badge>
                        {activeVersion && (
                            <div className="rounded-md border px-3 py-1.5 text-xs">
                                <div className="flex items-center gap-1 font-medium">
                                    <span className="size-1.5 rounded-full bg-emerald-500" />
                                    Version {activeVersion.version_no}
                                </div>
                                <div className="text-muted-foreground">
                                    Published on{' '}
                                    {formatDate(activeVersion.published_at)}
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                <form onSubmit={submit} className="flex flex-col gap-6">
                    <FormSection
                        icon={Coins}
                        color="blue"
                        title="Level Income Rates (L1 – L12)"
                        description="Percentage paid at each Sponsor/Direct level."
                        contentClassName="grid grid-cols-3 gap-3 sm:grid-cols-6"
                    >
                        {Object.entries(data.level_income_rates).map(
                            ([level, rate]) => (
                                <div key={level} className="grid gap-1">
                                    <Label className="text-xs">L{level}</Label>
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
                    </FormSection>

                    <FormSection
                        icon={Trophy}
                        color="amber"
                        title="Pair/Reward Milestones"
                        description="Pair value per entry: reward = (left + right) × pair value."
                        contentClassName="flex flex-col gap-3"
                    >
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
                        <div className="overflow-hidden rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow className="hover:bg-transparent">
                                        <TableHead className="w-10">
                                            #
                                        </TableHead>
                                        <TableHead>Milestone</TableHead>
                                        <TableHead>Min Directs</TableHead>
                                        <TableHead>Left</TableHead>
                                        <TableHead>Right</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {data.pair_milestones.map(
                                        (milestone, index) => (
                                            <TableRow
                                                key={milestone.milestone_no}
                                            >
                                                <TableCell>
                                                    {milestone.milestone_no}
                                                </TableCell>
                                                <TableCell>
                                                    Milestone #
                                                    {milestone.milestone_no}
                                                </TableCell>
                                                <TableCell>
                                                    <Input
                                                        type="number"
                                                        className="w-24"
                                                        value={
                                                            milestone.min_directs
                                                        }
                                                        onChange={(e) =>
                                                            setMilestone(
                                                                index,
                                                                'min_directs',
                                                                Number(
                                                                    e.target
                                                                        .value,
                                                                ),
                                                            )
                                                        }
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    <Input
                                                        type="number"
                                                        className="w-24"
                                                        value={milestone.left}
                                                        onChange={(e) =>
                                                            setMilestone(
                                                                index,
                                                                'left',
                                                                Number(
                                                                    e.target
                                                                        .value,
                                                                ),
                                                            )
                                                        }
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    <Input
                                                        type="number"
                                                        className="w-24"
                                                        value={milestone.right}
                                                        onChange={(e) =>
                                                            setMilestone(
                                                                index,
                                                                'right',
                                                                Number(
                                                                    e.target
                                                                        .value,
                                                                ),
                                                            )
                                                        }
                                                    />
                                                </TableCell>
                                            </TableRow>
                                        ),
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </FormSection>

                    <FormSection
                        icon={Users}
                        color="purple"
                        title="Pair Qualification Requirements"
                        description="Minimum completed EMIs per plan before an EMI joining fully qualifies for Pair/Reward."
                        contentClassName="grid grid-cols-2 gap-3 sm:grid-cols-4"
                    >
                        {Object.entries(data.pair_qualification_emis).map(
                            ([plan, count]) => (
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
                            ),
                        )}
                    </FormSection>

                    <FormSection
                        icon={TrendingUp}
                        color="green"
                        title="Income Booster Levels"
                        contentClassName="flex flex-col gap-3"
                    >
                        <div className="overflow-hidden rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow className="hover:bg-transparent">
                                        <TableHead>Level</TableHead>
                                        <TableHead>Min Directs</TableHead>
                                        <TableHead>Team Left</TableHead>
                                        <TableHead>Team Right</TableHead>
                                        <TableHead>
                                            Monthly Benefit (₹)
                                        </TableHead>
                                        <TableHead>Duration (mo)</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {data.booster_levels.map((level, index) => (
                                        <TableRow key={level.level_no}>
                                            <TableCell>
                                                Level {level.level_no}
                                            </TableCell>
                                            <TableCell>
                                                <Input
                                                    type="number"
                                                    className="w-24"
                                                    value={level.min_directs}
                                                    onChange={(e) =>
                                                        setBoosterLevel(
                                                            index,
                                                            'min_directs',
                                                            Number(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Input
                                                    type="number"
                                                    className="w-24"
                                                    value={
                                                        level.team_split_left
                                                    }
                                                    onChange={(e) =>
                                                        setBoosterLevel(
                                                            index,
                                                            'team_split_left',
                                                            Number(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Input
                                                    type="number"
                                                    className="w-24"
                                                    value={
                                                        level.team_split_right
                                                    }
                                                    onChange={(e) =>
                                                        setBoosterLevel(
                                                            index,
                                                            'team_split_right',
                                                            Number(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Input
                                                    type="number"
                                                    className="w-28"
                                                    value={
                                                        level.monthly_benefit
                                                    }
                                                    onChange={(e) =>
                                                        setBoosterLevel(
                                                            index,
                                                            'monthly_benefit',
                                                            Number(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Input
                                                    type="number"
                                                    className="w-20"
                                                    value={
                                                        level.duration_months
                                                    }
                                                    onChange={(e) =>
                                                        setBoosterLevel(
                                                            index,
                                                            'duration_months',
                                                            Number(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </FormSection>

                    <FormSection
                        icon={ShoppingCart}
                        color="teal"
                        title="Purchase/Repurchase Upline Rates"
                        contentClassName="grid grid-cols-3 gap-3 sm:grid-cols-7"
                    >
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
                    </FormSection>

                    <FormSection
                        icon={StoreIcon}
                        color="red"
                        title="Store Profit Distribution Rates"
                        contentClassName="grid grid-cols-2 gap-3 sm:grid-cols-4"
                    >
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
                            <Label className="text-xs">Item Buyback %</Label>
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
                            <Label className="text-xs">Store GST %</Label>
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
                    </FormSection>

                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <FormSection
                            icon={FileText}
                            color="purple"
                            title="Publish New Version"
                            description="Publishing creates a brand-new rule version effective today — the version above stays intact for historical calculations."
                            contentClassName="flex flex-col gap-3"
                        >
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
                        </FormSection>

                        <FormSection
                            icon={History}
                            color="blue"
                            title="Version History"
                            description="Previous rule versions and their details."
                        >
                            <VersionTimeline
                                entries={versions.map((version) => ({
                                    id: version.version_no,
                                    label: `Version ${version.version_no}`,
                                    date: formatDate(version.published_at),
                                    by: version.published_by,
                                    note: version.notes,
                                    changes: version.changes,
                                    active: version.is_active,
                                }))}
                            />
                        </FormSection>
                    </div>
                </form>
            </div>
        </>
    );
}

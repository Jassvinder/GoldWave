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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { VersionTimeline } from '@/components/version-timeline';
import { store } from '@/routes/super-admin/rule-versions';

type Milestone = {
    milestone_no: number;
    name?: string;
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
        level_income_rates_gold: Record<string, number>;
        level_income_min_directs: Record<string, number>;
        pair_value_per_entry: number;
        pair_value_per_entry_gold: number;
        pair_milestones: Milestone[];
        pair_qualification_emis: Record<string, number>;
        booster_levels: BoosterLevel[];
        purchase_repurchase_income_rates: Record<string, number>;
        purchase_repurchase_income_rates_gold: Record<string, number>;
        store_profit_distribution_rates: Record<string, number>;
        store_profit_distribution_rates_gold: Record<string, number>;
        item_buyback_percent: number;
        item_buyback_percent_gold: number;
        store_gst_percent: number;
        store_income_min_directs: number;
        store_emi_break_overdue_count: number;
    };
};

/** INSTRUCTIONS.md S03 (also Admin Compensation Management's config page) — level percentages, pair/booster/store rates, effective dates/versions. */
export default function SuperAdminRuleVersions({ versions, current }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const activeVersion = versions.find((version) => version.is_active);
    const { data, setData, post, processing } = useForm({
        notes: '',
        level_income_rates: current.level_income_rates,
        level_income_min_directs: current.level_income_min_directs,
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
        store_income_min_directs: current.store_income_min_directs,
        store_emi_break_overdue_count: current.store_emi_break_overdue_count,
    });

    const {
        data: goldData,
        setData: setGoldData,
        post: postGold,
        processing: goldProcessing,
    } = useForm({
        notes: '',
        level_income_rates_gold: current.level_income_rates_gold,
        pair_value_per_entry_gold: current.pair_value_per_entry_gold,
        purchase_repurchase_income_rates_gold:
            current.purchase_repurchase_income_rates_gold,
        store_profit_distribution_rates_gold:
            current.store_profit_distribution_rates_gold,
        item_buyback_percent_gold: current.item_buyback_percent_gold,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(store.url());
    };

    const submitGold: FormEventHandler = (e) => {
        e.preventDefault();
        postGold(store.url());
    };

    const setLevelRate = (level: string, value: number) => {
        setData('level_income_rates', {
            ...data.level_income_rates,
            [level]: value,
        });
    };

    const setGoldLevelRate = (level: string, value: number) => {
        setGoldData('level_income_rates_gold', {
            ...goldData.level_income_rates_gold,
            [level]: value,
        });
    };

    const setLevelMinDirects = (level: string, value: number) => {
        setData('level_income_min_directs', {
            ...data.level_income_min_directs,
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

    const setGoldPurchaseRate = (level: string, value: number) => {
        setGoldData('purchase_repurchase_income_rates_gold', {
            ...goldData.purchase_repurchase_income_rates_gold,
            [level]: value,
        });
    };

    const setStoreRate = (key: string, value: number) => {
        setData('store_profit_distribution_rates', {
            ...data.store_profit_distribution_rates,
            [key]: value,
        });
    };

    const setGoldStoreRate = (key: string, value: number) => {
        setGoldData('store_profit_distribution_rates_gold', {
            ...goldData.store_profit_distribution_rates_gold,
            [key]: value,
        });
    };

    const setMilestone = (
        index: number,
        field: keyof Milestone,
        value: number | string,
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

                <Tabs defaultValue="silver">
                    <TabsList>
                        <TabsTrigger value="silver">Silver</TabsTrigger>
                        <TabsTrigger value="gold">Gold</TabsTrigger>
                    </TabsList>

                    <TabsContent value="silver">
                        <form onSubmit={submit} className="flex flex-col gap-6">
                            <FormSection
                                icon={Coins}
                                color="blue"
                                title="Level Income Rates (L1 – L12) — Silver"
                                description="Percentage paid at each Sponsor/Direct level, for a Silver-plan payment."
                                contentClassName="grid grid-cols-3 gap-3 sm:grid-cols-6"
                            >
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
                            </FormSection>

                            <FormSection
                                icon={Users}
                                color="blue"
                                title="Level Income — Directs Needed (L1 – L12)"
                                description="Total qualified directs a member needs to earn each level (Silver and Gold alike). Short of it, that level's income lapses. An EMI direct counts once its Pair qualification EMIs are paid."
                                contentClassName="grid grid-cols-3 gap-3 sm:grid-cols-6"
                            >
                                {Object.entries(
                                    data.level_income_min_directs,
                                ).map(([level, directs]) => (
                                    <div key={level} className="grid gap-1">
                                        <Label className="text-xs">
                                            L{level}
                                        </Label>
                                        <Input
                                            type="number"
                                            min={0}
                                            step="1"
                                            value={directs}
                                            onChange={(e) =>
                                                setLevelMinDirects(
                                                    level,
                                                    Number(e.target.value),
                                                )
                                            }
                                        />
                                    </div>
                                ))}
                            </FormSection>

                            <FormSection
                                icon={Trophy}
                                color="amber"
                                title="Pair/Reward Milestones"
                                description="Reward = sum of each consumed entry's own metal value. Milestone thresholds below apply to a mixed Gold+Silver team, unchanged by metal."
                                contentClassName="flex flex-col gap-3"
                            >
                                <div className="grid max-w-xs gap-1">
                                    <Label className="text-xs">
                                        Pair Value Per Entry — Silver
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
                                                <TableHead>Name</TableHead>
                                                <TableHead>
                                                    Total Directs
                                                </TableHead>
                                                <TableHead>Left</TableHead>
                                                <TableHead>Right</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {data.pair_milestones.map(
                                                (milestone, index) => (
                                                    <TableRow
                                                        key={
                                                            milestone.milestone_no
                                                        }
                                                    >
                                                        <TableCell>
                                                            {
                                                                milestone.milestone_no
                                                            }
                                                        </TableCell>
                                                        <TableCell>
                                                            <Input
                                                                type="text"
                                                                className="w-48"
                                                                maxLength={60}
                                                                placeholder={`Milestone #${milestone.milestone_no}`}
                                                                value={
                                                                    milestone.name ??
                                                                    ''
                                                                }
                                                                onChange={(e) =>
                                                                    setMilestone(
                                                                        index,
                                                                        'name',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
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
                                                                            e
                                                                                .target
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
                                                                value={
                                                                    milestone.left
                                                                }
                                                                onChange={(e) =>
                                                                    setMilestone(
                                                                        index,
                                                                        'left',
                                                                        Number(
                                                                            e
                                                                                .target
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
                                                                value={
                                                                    milestone.right
                                                                }
                                                                onChange={(e) =>
                                                                    setMilestone(
                                                                        index,
                                                                        'right',
                                                                        Number(
                                                                            e
                                                                                .target
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
                                                <TableHead>
                                                    Min Directs
                                                </TableHead>
                                                <TableHead>Team Left</TableHead>
                                                <TableHead>
                                                    Team Right
                                                </TableHead>
                                                <TableHead>
                                                    Monthly Benefit (₹)
                                                </TableHead>
                                                <TableHead>
                                                    Duration (mo)
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {data.booster_levels.map(
                                                (level, index) => (
                                                    <TableRow
                                                        key={level.level_no}
                                                    >
                                                        <TableCell>
                                                            Level{' '}
                                                            {level.level_no}
                                                        </TableCell>
                                                        <TableCell>
                                                            <Input
                                                                type="number"
                                                                className="w-24"
                                                                value={
                                                                    level.min_directs
                                                                }
                                                                onChange={(e) =>
                                                                    setBoosterLevel(
                                                                        index,
                                                                        'min_directs',
                                                                        Number(
                                                                            e
                                                                                .target
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
                                                                value={
                                                                    level.team_split_left
                                                                }
                                                                onChange={(e) =>
                                                                    setBoosterLevel(
                                                                        index,
                                                                        'team_split_left',
                                                                        Number(
                                                                            e
                                                                                .target
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
                                                                value={
                                                                    level.team_split_right
                                                                }
                                                                onChange={(e) =>
                                                                    setBoosterLevel(
                                                                        index,
                                                                        'team_split_right',
                                                                        Number(
                                                                            e
                                                                                .target
                                                                                .value,
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
                                                                            e
                                                                                .target
                                                                                .value,
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
                                                                            e
                                                                                .target
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
                                icon={ShoppingCart}
                                color="teal"
                                title="Purchase/Repurchase Upline Rates — Silver"
                                contentClassName="grid grid-cols-3 gap-3 sm:grid-cols-7"
                            >
                                {Object.entries(
                                    data.purchase_repurchase_income_rates,
                                ).map(([level, rate]) => (
                                    <div key={level} className="grid gap-1">
                                        <Label className="text-xs capitalize">
                                            {level === 'self'
                                                ? 'Self'
                                                : `L${level}`}
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
                                title="Store Profit Distribution Rates — Silver"
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
                                    <Label className="text-xs">
                                        Item Buyback % — Silver
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
                                        Store GST % (shared, not metal-split)
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
                                <div className="grid gap-1 sm:col-span-2">
                                    <Label className="text-xs">
                                        Directs to unlock store upline income
                                        (shared, not metal-split)
                                    </Label>
                                    <Input
                                        type="number"
                                        min={0}
                                        step="1"
                                        value={data.store_income_min_directs}
                                        onChange={(e) =>
                                            setData(
                                                'store_income_min_directs',
                                                Number(e.target.value),
                                            )
                                        }
                                    />
                                    <p className="text-muted-foreground text-xs">
                                        Qualified directs a member needs before
                                        earning Purchase/Repurchase L1–12 and
                                        Store Profit sponsor levels. Once
                                        reached it stays unlocked for life;
                                        before that the income lapses. 0 = no
                                        condition.
                                    </p>
                                </div>
                                <div className="grid gap-1 sm:col-span-2">
                                    <Label className="text-xs">
                                        Repurchase on EMI — overdue EMIs that
                                        break it
                                    </Label>
                                    <Input
                                        type="number"
                                        min={1}
                                        max={24}
                                        step="1"
                                        value={
                                            data.store_emi_break_overdue_count
                                        }
                                        onChange={(e) =>
                                            setData(
                                                'store_emi_break_overdue_count',
                                                Number(e.target.value),
                                            )
                                        }
                                    />
                                    <p className="text-muted-foreground text-xs">
                                        At this many overdue EMIs the booking
                                        closes by itself: the piece returns to
                                        stock and the member is owed silver for
                                        the principal paid.
                                    </p>
                                </div>
                            </FormSection>

                            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                                <FormSection
                                    icon={FileText}
                                    color="purple"
                                    title="Publish New Version — Silver"
                                    description="Publishing creates a brand-new rule version effective today — the version above stays intact for historical calculations. Only Silver's fields on this tab are submitted; Gold's own values carry forward unchanged."
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
                                    description="Previous rule versions and their details — shared across Silver and Gold."
                                >
                                    <VersionTimeline
                                        entries={versions.map((version) => ({
                                            id: version.version_no,
                                            label: `Version ${version.version_no}`,
                                            date: formatDate(
                                                version.published_at,
                                            ),
                                            by: version.published_by,
                                            note: version.notes,
                                            changes: version.changes,
                                            active: version.is_active,
                                        }))}
                                    />
                                </FormSection>
                            </div>
                        </form>
                    </TabsContent>

                    <TabsContent value="gold">
                        <form
                            onSubmit={submitGold}
                            className="flex flex-col gap-6"
                        >
                            <FormSection
                                icon={Coins}
                                color="amber"
                                title="Level Income Rates (L1 – L12) — Gold"
                                description="Percentage paid at each Sponsor/Direct level, for a Gold-plan payment."
                                contentClassName="grid grid-cols-3 gap-3 sm:grid-cols-6"
                            >
                                {Object.entries(
                                    goldData.level_income_rates_gold,
                                ).map(([level, rate]) => (
                                    <div key={level} className="grid gap-1">
                                        <Label className="text-xs">
                                            L{level}
                                        </Label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            value={rate}
                                            onChange={(e) =>
                                                setGoldLevelRate(
                                                    level,
                                                    Number(e.target.value),
                                                )
                                            }
                                        />
                                    </div>
                                ))}
                            </FormSection>

                            <FormSection
                                icon={Trophy}
                                color="amber"
                                title="Pair/Reward Value — Gold"
                                description="Reward = sum of each consumed entry's own metal value. Milestone thresholds are configured once, on the Silver tab, and apply to a mixed Gold+Silver team regardless of metal."
                                contentClassName="flex flex-col gap-3"
                            >
                                <div className="grid max-w-xs gap-1">
                                    <Label className="text-xs">
                                        Pair Value Per Entry — Gold
                                    </Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        value={
                                            goldData.pair_value_per_entry_gold
                                        }
                                        onChange={(e) =>
                                            setGoldData(
                                                'pair_value_per_entry_gold',
                                                Number(e.target.value),
                                            )
                                        }
                                    />
                                </div>
                            </FormSection>

                            <FormSection
                                icon={ShoppingCart}
                                color="amber"
                                title="Purchase/Repurchase Upline Rates — Gold"
                                contentClassName="grid grid-cols-3 gap-3 sm:grid-cols-7"
                            >
                                {Object.entries(
                                    goldData.purchase_repurchase_income_rates_gold,
                                ).map(([level, rate]) => (
                                    <div key={level} className="grid gap-1">
                                        <Label className="text-xs capitalize">
                                            {level === 'self'
                                                ? 'Self'
                                                : `L${level}`}
                                        </Label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            value={rate}
                                            onChange={(e) =>
                                                setGoldPurchaseRate(
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
                                color="amber"
                                title="Store Profit Distribution Rates — Gold"
                                contentClassName="grid grid-cols-2 gap-3 sm:grid-cols-4"
                            >
                                {Object.entries(
                                    goldData.store_profit_distribution_rates_gold,
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
                                                setGoldStoreRate(
                                                    key,
                                                    Number(e.target.value),
                                                )
                                            }
                                        />
                                    </div>
                                ))}
                                <div className="grid gap-1">
                                    <Label className="text-xs">
                                        Item Buyback % — Gold
                                    </Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        value={
                                            goldData.item_buyback_percent_gold
                                        }
                                        onChange={(e) =>
                                            setGoldData(
                                                'item_buyback_percent_gold',
                                                Number(e.target.value),
                                            )
                                        }
                                    />
                                </div>
                            </FormSection>

                            <FormSection
                                icon={FileText}
                                color="purple"
                                title="Publish New Version — Gold"
                                description="Publishing creates a brand-new rule version effective today. Only Gold's fields on this tab are submitted; Silver's own values carry forward unchanged."
                                contentClassName="flex flex-col gap-3"
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="gold_notes">Notes</Label>
                                    <textarea
                                        id="gold_notes"
                                        className="border-input bg-background rounded-md border px-3 py-2 text-sm"
                                        rows={3}
                                        value={goldData.notes}
                                        onChange={(e) =>
                                            setGoldData('notes', e.target.value)
                                        }
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={goldProcessing}
                                    className="self-start"
                                >
                                    Publish New Version
                                </Button>
                            </FormSection>
                        </form>
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}

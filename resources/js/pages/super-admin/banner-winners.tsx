import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Award, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, type ReactNode, useState } from 'react';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { FormSection } from '@/components/form-section';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/utils';
import { destroy, store, update } from '@/routes/super-admin/banner-winners';

type BannerWinner = {
    id: number;
    group_no: number;
    /** YYYY-MM */
    draw_month: string;
    month_label: string;
    customer_id: string;
    name: string | null;
    prize: string;
    on_banner: boolean;
    updated: string | null;
    updated_by: string | null;
};

/** The current month as YYYY-MM, the default for a new winner. */
function thisMonth(): string {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
}

/**
 * T-248 (INSTRUCTIONS.md S20) — Super Admin or the company Admin lists the draw winners shown on the member login
 * banner. Display only: nothing here changes Draw Management, a wallet or a payout.
 */
export default function BannerWinners({
    winners,
}: {
    winners: BannerWinner[];
}) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const bannerMonth = winners.find((row) => row.on_banner)?.month_label;

    const columns: DataTableColumn<BannerWinner>[] = [
        {
            key: 'month',
            header: 'Month',
            render: (row) => (
                <div className="flex flex-wrap items-center gap-2">
                    <span className="font-medium">{row.month_label}</span>
                    {row.on_banner && (
                        <Badge
                            variant="outline"
                            className="border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200"
                        >
                            On banner
                        </Badge>
                    )}
                </div>
            ),
        },
        {
            key: 'group',
            header: 'Group',
            render: (row) => `Group ${row.group_no}`,
        },
        {
            key: 'winner',
            header: 'Winner',
            render: (row) => (
                <div className="flex flex-col">
                    <span className="font-medium">{row.name ?? '—'}</span>
                    <span className="text-muted-foreground font-mono text-xs">
                        {row.customer_id}
                    </span>
                </div>
            ),
        },
        { key: 'prize', header: 'Prize', render: (row) => row.prize },
        {
            key: 'updated',
            header: 'Last changed',
            render: (row) => (
                <span className="text-xs">
                    {formatDate(row.updated)}
                    {row.updated_by ? ` · ${row.updated_by}` : ''}
                </span>
            ),
        },
    ];

    return (
        <>
            <Head title="Banner Winners" />

            <div className="flex w-full flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <FormSection
                    icon={Award}
                    color="amber"
                    title="Banner Winners"
                    description={
                        bannerMonth
                            ? `Draw winners shown on the welcome banner members see after every login. The banner shows the latest month only — now ${bannerMonth}. Only for display: it does not change Draw Management, wallets or payouts.`
                            : 'Draw winners shown on the welcome banner members see after every login. The banner shows the latest month only. Only for display: it does not change Draw Management, wallets or payouts.'
                    }
                    action={
                        <WinnerDialog
                            trigger={
                                <Button size="sm">
                                    <Plus className="size-4" />
                                    Add Winner
                                </Button>
                            }
                        />
                    }
                >
                    <DataTable
                        columns={columns}
                        rows={winners}
                        rowKey={(row) => row.id}
                        emptyMessage="No winners yet. The banner shows today's rates without a winners list until you add one."
                        renderActions={(row) => (
                            <div className="flex justify-end gap-1">
                                <WinnerDialog
                                    winner={row}
                                    trigger={
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            aria-label={`Edit ${row.customer_id}`}
                                        >
                                            <Pencil className="size-4" />
                                        </Button>
                                    }
                                />
                                <DeleteWinnerDialog winner={row} />
                            </div>
                        )}
                    />
                </FormSection>
            </div>
        </>
    );
}

/** Add (no `winner`) or edit one winner. The name comes from the member with that Customer ID. */
function WinnerDialog({
    winner,
    trigger,
}: {
    winner?: BannerWinner;
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm(
        winner
            ? {
                  group_no: String(winner.group_no),
                  draw_month: winner.draw_month,
                  customer_id: winner.customer_id,
                  prize: winner.prize,
              }
            : {
                  group_no: '',
                  draw_month: thisMonth(),
                  customer_id: '',
                  prize: '',
              },
    );

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);

                if (!winner) {
                    // Keep the group, month and prize: the next winner is usually from the same draw.
                    form.setData('customer_id', '');
                }
            },
        };

        if (winner) {
            form.patch(update.url(winner.id), options);
        } else {
            form.post(store.url(), options);
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            {winner ? 'Edit Winner' : 'Add Winner'}
                        </DialogTitle>
                        <DialogDescription>
                            Enter the Customer ID; the winner&apos;s name is
                            taken from their account.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="winner-month">Draw month</Label>
                            <Input
                                id="winner-month"
                                type="month"
                                value={form.data.draw_month}
                                onChange={(e) =>
                                    form.setData('draw_month', e.target.value)
                                }
                                required
                            />
                            <InputError message={form.errors.draw_month} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="winner-group">Group No.</Label>
                            <Input
                                id="winner-group"
                                type="number"
                                inputMode="numeric"
                                min={1}
                                max={9999}
                                value={form.data.group_no}
                                onChange={(e) =>
                                    form.setData('group_no', e.target.value)
                                }
                                placeholder="e.g. 1"
                                required
                            />
                            <InputError message={form.errors.group_no} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="winner-customer-id">Customer ID</Label>
                        <Input
                            id="winner-customer-id"
                            maxLength={50}
                            autoComplete="off"
                            className="font-mono uppercase"
                            value={form.data.customer_id}
                            onChange={(e) =>
                                form.setData('customer_id', e.target.value)
                            }
                            placeholder="e.g. GWL123"
                            required
                        />
                        <InputError message={form.errors.customer_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="winner-prize">Prize</Label>
                        <Input
                            id="winner-prize"
                            maxLength={120}
                            value={form.data.prize}
                            onChange={(e) =>
                                form.setData('prize', e.target.value)
                            }
                            placeholder="e.g. Silver Jewellery"
                            required
                        />
                        <InputError message={form.errors.prize} />
                    </div>

                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            {winner ? 'Save' : 'Add Winner'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteWinnerDialog({ winner }: { winner: BannerWinner }) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const remove = () => {
        router.delete(destroy.url(winner.id), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    size="icon"
                    variant="ghost"
                    aria-label={`Remove ${winner.customer_id}`}
                >
                    <Trash2 className="text-destructive size-4" />
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Remove this winner?</DialogTitle>
                    <DialogDescription>
                        {winner.name ?? winner.customer_id} (
                        {winner.customer_id}
                        ), Group {winner.group_no}, {winner.month_label} is
                        removed from the list and from the banner.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Keep it
                    </Button>
                    <Button
                        variant="destructive"
                        disabled={processing}
                        onClick={remove}
                    >
                        Remove
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

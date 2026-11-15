import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Megaphone, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, type ReactNode, useState } from 'react';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import {
    NOTICE_KIND,
    type NoticeKind,
} from '@/components/dashboard-highlights';
import { FormSection } from '@/components/form-section';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate } from '@/lib/utils';
import { destroy, store, update } from '@/routes/super-admin/dashboard-notices';

type NoticeState = 'showing' | 'scheduled' | 'ended' | 'off';

type Notice = {
    id: number;
    kind: NoticeKind;
    title: string;
    body: string | null;
    starts_on: string | null;
    ends_on: string | null;
    is_active: boolean;
    state: NoticeState;
    updated: string | null;
    updated_by: string | null;
};

const STATE: Record<NoticeState, { label: string; className: string }> = {
    showing: {
        label: 'Showing now',
        className:
            'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
    },
    scheduled: {
        label: 'Scheduled',
        className:
            'border-blue-300 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-200',
    },
    ended: { label: 'Ended', className: '' },
    off: { label: 'Turned off', className: '' },
};

/** "Always", "From 01-12-2026", "Till 31-12-2026" or "01-12-2026 to 31-12-2026". */
function period(notice: Pick<Notice, 'starts_on' | 'ends_on'>): string {
    if (notice.starts_on && notice.ends_on) {
        return `${formatDate(notice.starts_on)} to ${formatDate(notice.ends_on)}`;
    }

    if (notice.starts_on) {
        return `From ${formatDate(notice.starts_on)}`;
    }

    return notice.ends_on ? `Till ${formatDate(notice.ends_on)}` : 'Always';
}

/**
 * T-245 (INSTRUCTIONS.md S19) — Super Admin or the company Admin manages the offers, events and notices at the top of
 * every member's dashboard. Today's gold/silver rate shows there automatically from Gold & Silver Rates.
 */
export default function DashboardNotices({ notices }: { notices: Notice[] }) {
    const flash = usePage().props.flash as { status?: string } | undefined;

    const columns: DataTableColumn<Notice>[] = [
        {
            key: 'kind',
            header: 'Type',
            render: (row) => {
                const kind = NOTICE_KIND[row.kind];
                const Icon = kind.icon;

                return (
                    <Badge variant="outline" className={kind.className}>
                        <Icon className="size-3" />
                        {kind.label}
                    </Badge>
                );
            },
        },
        {
            key: 'title',
            header: 'Title',
            render: (row) => (
                <div className="flex max-w-md flex-col">
                    <span className="font-medium">{row.title}</span>
                    {row.body && (
                        <span className="text-muted-foreground line-clamp-2 text-xs whitespace-pre-line">
                            {row.body}
                        </span>
                    )}
                </div>
            ),
        },
        { key: 'period', header: 'Shows', render: (row) => period(row) },
        {
            key: 'state',
            header: 'Status',
            render: (row) => (
                <Badge
                    variant={
                        row.state === 'showing' || row.state === 'scheduled'
                            ? 'outline'
                            : 'secondary'
                    }
                    className={STATE[row.state].className}
                >
                    {STATE[row.state].label}
                </Badge>
            ),
        },
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
            <Head title="Dashboard Notices" />

            <div className="flex w-full flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <FormSection
                    icon={Megaphone}
                    color="purple"
                    title="Dashboard Notices"
                    description="Offers, events and notices shown at the top of every member's dashboard, next to today's gold and silver rate. Set dates to show a notice only for a period; turn it off to hide it without deleting."
                    action={
                        <NoticeDialog
                            trigger={
                                <Button size="sm">
                                    <Plus className="size-4" />
                                    Add Notice
                                </Button>
                            }
                        />
                    }
                >
                    <DataTable
                        columns={columns}
                        rows={notices}
                        rowKey={(row) => row.id}
                        emptyMessage="No notices yet. Members see only today's rates until you add one."
                        renderActions={(row) => (
                            <div className="flex justify-end gap-1">
                                <NoticeDialog
                                    notice={row}
                                    trigger={
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            aria-label={`Edit ${row.title}`}
                                        >
                                            <Pencil className="size-4" />
                                        </Button>
                                    }
                                />
                                <DeleteNoticeDialog notice={row} />
                            </div>
                        )}
                    />
                </FormSection>
            </div>
        </>
    );
}

/** Add (no `notice`) or edit one notice. */
function NoticeDialog({
    notice,
    trigger,
}: {
    notice?: Notice;
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const blank = {
        kind: 'offer' as NoticeKind,
        title: '',
        body: '',
        starts_on: '',
        ends_on: '',
        is_active: true,
    };
    const form = useForm(
        notice
            ? {
                  kind: notice.kind,
                  title: notice.title,
                  body: notice.body ?? '',
                  starts_on: notice.starts_on ?? '',
                  ends_on: notice.ends_on ?? '',
                  is_active: notice.is_active,
              }
            : blank,
    );

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);

                if (!notice) {
                    form.reset();
                }
            },
        };

        form.transform((data) => ({
            ...data,
            body: data.body || null,
            starts_on: data.starts_on || null,
            ends_on: data.ends_on || null,
        }));

        if (notice) {
            form.patch(update.url(notice.id), options);
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
                            {notice ? 'Edit Notice' : 'Add Notice'}
                        </DialogTitle>
                        <DialogDescription>
                            Shown at the top of every member&apos;s dashboard.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div className="grid gap-2">
                            <Label>Type</Label>
                            <Select
                                value={form.data.kind}
                                onValueChange={(value) =>
                                    form.setData('kind', value as NoticeKind)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="offer">Offer</SelectItem>
                                    <SelectItem value="event">Event</SelectItem>
                                    <SelectItem value="notice">
                                        Notice
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.kind} />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="notice-title">Title</Label>
                            <Input
                                id="notice-title"
                                maxLength={120}
                                value={form.data.title}
                                onChange={(e) =>
                                    form.setData('title', e.target.value)
                                }
                                placeholder="e.g. Diwali offer: no making charges"
                                required
                            />
                            <InputError message={form.errors.title} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="notice-body">Details (optional)</Label>
                        <textarea
                            id="notice-body"
                            className="border-input bg-background rounded-md border px-3 py-2 text-sm"
                            rows={4}
                            maxLength={1000}
                            value={form.data.body}
                            onChange={(e) =>
                                form.setData('body', e.target.value)
                            }
                        />
                        <InputError message={form.errors.body} />
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="notice-starts">
                                Show from (optional)
                            </Label>
                            <Input
                                id="notice-starts"
                                type="date"
                                value={form.data.starts_on}
                                onChange={(e) =>
                                    form.setData('starts_on', e.target.value)
                                }
                            />
                            <InputError message={form.errors.starts_on} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="notice-ends">
                                Show till (optional)
                            </Label>
                            <Input
                                id="notice-ends"
                                type="date"
                                min={form.data.starts_on || undefined}
                                value={form.data.ends_on}
                                onChange={(e) =>
                                    form.setData('ends_on', e.target.value)
                                }
                            />
                            <InputError message={form.errors.ends_on} />
                        </div>
                    </div>

                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={form.data.is_active}
                            onCheckedChange={(checked) =>
                                form.setData('is_active', checked === true)
                            }
                        />
                        Show this notice (untick to hide it without deleting)
                    </label>

                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            {notice ? 'Save' : 'Add Notice'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteNoticeDialog({ notice }: { notice: Notice }) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const remove = () => {
        router.delete(destroy.url(notice.id), {
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
                    aria-label={`Delete ${notice.title}`}
                >
                    <Trash2 className="text-destructive size-4" />
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Delete this notice?</DialogTitle>
                    <DialogDescription>
                        &ldquo;{notice.title}&rdquo; is removed from every
                        member&apos;s dashboard. To hide it for now, edit it and
                        untick &ldquo;Show this notice&rdquo; instead.
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
                        Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

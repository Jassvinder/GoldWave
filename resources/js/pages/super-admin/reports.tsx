import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable, type DataTableColumn } from '@/components/data-table';
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
import { download, store } from '@/routes/super-admin/reports';

type Export = {
    id: number;
    report_type: string;
    format: string;
    status: string;
    row_count: number | null;
    error_message: string | null;
    requested_at: string | null;
};

type Props = { report_types: Record<string, string>; exports: Export[] };

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'destructive'> =
    {
        pending: 'secondary',
        processing: 'secondary',
        ready: 'default',
        failed: 'destructive',
    };

/** INSTRUCTIONS.md "Reports" (Super Admin) — the full cross-module report catalog with queued exports, T-018. */
export default function SuperAdminReports({ report_types, exports }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const { data, setData, post, processing, errors } = useForm({
        report_type: Object.keys(report_types)[0] ?? '',
        format: 'csv',
        date_from: '',
        date_to: '',
        customer_id: '',
        store_id: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(store.url());
    };

    return (
        <>
            <Head title="Reports" />

            <div className="flex w-full flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Request Report Export
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submit}
                            className="grid grid-cols-1 gap-3 sm:grid-cols-3"
                        >
                            <div className="grid gap-2">
                                <Label>Report Type</Label>
                                <Select
                                    value={data.report_type}
                                    onValueChange={(v) =>
                                        setData('report_type', v)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(report_types).map(
                                            ([key, label]) => (
                                                <SelectItem
                                                    key={key}
                                                    value={key}
                                                >
                                                    {label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label>Format</Label>
                                <Select
                                    value={data.format}
                                    onValueChange={(v) => setData('format', v)}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="csv">CSV</SelectItem>
                                        <SelectItem value="xlsx">
                                            Excel (.xlsx)
                                        </SelectItem>
                                        <SelectItem value="pdf">PDF</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div />
                            <div className="grid gap-2">
                                <Label htmlFor="date_from">
                                    Date From (optional)
                                </Label>
                                <Input
                                    id="date_from"
                                    type="date"
                                    value={data.date_from}
                                    onChange={(e) =>
                                        setData('date_from', e.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="date_to">
                                    Date To (optional)
                                </Label>
                                <Input
                                    id="date_to"
                                    type="date"
                                    value={data.date_to}
                                    onChange={(e) =>
                                        setData('date_to', e.target.value)
                                    }
                                />
                            </div>
                            <div />
                            <div className="grid gap-2">
                                <Label htmlFor="customer_id">
                                    Customer ID (optional)
                                </Label>
                                <Input
                                    id="customer_id"
                                    value={data.customer_id}
                                    onChange={(e) =>
                                        setData('customer_id', e.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="store_id">
                                    Store ID (optional)
                                </Label>
                                <Input
                                    id="store_id"
                                    type="number"
                                    value={data.store_id}
                                    onChange={(e) =>
                                        setData('store_id', e.target.value)
                                    }
                                />
                            </div>
                            {errors.report_type && (
                                <p className="text-destructive text-xs sm:col-span-3">
                                    {errors.report_type}
                                </p>
                            )}
                            <Button
                                type="submit"
                                disabled={processing}
                                className="sm:col-span-3 sm:self-start"
                            >
                                Request Export
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Your Export Requests</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            columns={exportColumns}
                            rows={exports}
                            rowKey={(row) => row.id}
                            emptyMessage="No report exports requested yet."
                            renderActions={(row) =>
                                row.status === 'ready' ? (
                                    <Button variant="outline" size="sm" asChild>
                                        <a href={download.url(row.id)}>
                                            Download
                                        </a>
                                    </Button>
                                ) : null
                            }
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

const exportColumns: DataTableColumn<Export>[] = [
    {
        key: 'report_type',
        header: 'Report',
        render: (row) => (
            <span className="font-medium">
                {row.report_type} ({row.format})
            </span>
        ),
    },
    {
        key: 'row_count',
        header: 'Rows',
        render: (row) => (row.row_count !== null ? row.row_count : '—'),
    },
    {
        key: 'requested_at',
        header: 'Requested',
        render: (row) => formatDate(row.requested_at),
    },
    {
        key: 'status',
        header: 'Status',
        render: (row) => (
            <div className="flex flex-col gap-0.5">
                <Badge variant={STATUS_VARIANT[row.status] ?? 'secondary'}>
                    {row.status}
                </Badge>
                {row.status === 'failed' && row.error_message && (
                    <span className="text-muted-foreground text-xs">
                        {row.error_message}
                    </span>
                )}
            </div>
        ),
    },
];

import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { exportMethod as exportReport } from '@/routes/member/reports';

type Props = { reports: Record<string, string> };

type ReportRow = { key: string; label: string };

const columns: DataTableColumn<ReportRow>[] = [
    { key: 'label', header: 'Report' },
];

/**
 * INSTRUCTIONS.md M17 — own downloadable reports. The full multi-report
 * catalog across every role/module (INSTRUCTIONS.md's "Reports" section) is
 * T-018's own dedicated task; this page covers only a member's own data.
 */
export default function Reports({ reports }: Props) {
    const rows: ReportRow[] = Object.entries(reports).map(([key, label]) => ({
        key,
        label,
    }));

    return (
        <>
            <Head title="Reports" />

            <div className="flex w-full flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">My Reports</CardTitle>
                        <CardDescription>
                            Download your own data as CSV.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            columns={columns}
                            rows={rows}
                            rowKey={(row) => row.key}
                            renderActions={(row) => (
                                <Button variant="outline" size="sm" asChild>
                                    <a href={exportReport.url(row.key)}>
                                        Download CSV
                                    </a>
                                </Button>
                            )}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

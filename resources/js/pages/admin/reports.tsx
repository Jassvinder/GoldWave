import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { exportMethod as exportReport } from '@/routes/admin/reports';

type Props = { reports: Record<string, string> };

/** INSTRUCTIONS.md A06 — assigned-store sales, inventory, profit, and distribution reports. */
export default function AdminReports({ reports }: Props) {
    return (
        <>
            <Head title="Store Reports" />

            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Store Reports
                        </CardTitle>
                        <CardDescription>
                            Download this store's data as CSV.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {Object.entries(reports).map(([key, label]) => (
                            <div
                                key={key}
                                className="flex items-center justify-between rounded-md border p-3"
                            >
                                <span>{label}</span>
                                <Button variant="outline" size="sm" asChild>
                                    <a href={exportReport.url(key)}>
                                        Download CSV
                                    </a>
                                </Button>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

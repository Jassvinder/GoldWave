import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { exportMethod as exportReport } from '@/routes/member/reports';

type Props = { reports: Record<string, string> };

/**
 * INSTRUCTIONS.md M17 — own downloadable reports. The full multi-report
 * catalog across every role/module (INSTRUCTIONS.md's "Reports" section) is
 * T-018's own dedicated task; this page covers only a member's own data.
 */
export default function Reports({ reports }: Props) {
    return (
        <>
            <Head title="Reports" />

            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">My Reports</CardTitle>
                        <CardDescription>
                            Download your own data as CSV.
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

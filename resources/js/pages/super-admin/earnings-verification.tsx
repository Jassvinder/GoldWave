import { Head, router } from '@inertiajs/react';
import { CheckCircle2, ChevronDown, ShieldCheck, XCircle } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/utils';
import { index } from '@/routes/super-admin/earnings-verification';

type Finding = { severity: 'error' | 'warning'; message: string; ref: string };

type CheckResult = {
    label: string;
    checked: number;
    errors: number;
    warnings: number;
    findings: Finding[];
};

type Report = {
    ran_at: string;
    errors: number;
    warnings: number;
    checks: Record<string, CheckResult>;
};

type Props = {
    checks: Record<string, string>;
    report: Report | null;
};

/**
 * "Earnings Verification" — re-derives every stored earning from its source event and compares it with what the
 * compensation Actions wrote (same engine as `php artisan earnings:verify`). Read-only: it never changes or corrects data.
 */
export default function EarningsVerification({ checks, report }: Props) {
    const [running, setRunning] = useState(false);

    const run = () =>
        router.get(
            index.url(),
            { run: 1 },
            {
                onStart: () => setRunning(true),
                onFinish: () => setRunning(false),
            },
        );

    return (
        <>
            <Head title="Earnings Verification" />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Earnings Verification
                        </h1>
                        <p className="text-muted-foreground max-w-2xl text-sm">
                            Re-calculates every earning from the original
                            payments, store sales and member tree, and compares
                            it with what was actually credited. It only reads —
                            nothing is changed or corrected.
                        </p>
                    </div>
                    <Button onClick={run} disabled={running}>
                        {running ? (
                            <Spinner />
                        ) : (
                            <ShieldCheck className="size-4" />
                        )}
                        {running
                            ? 'Checking…'
                            : report
                              ? 'Run again'
                              : 'Run verification'}
                    </Button>
                </div>

                {!report && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                What gets checked
                            </CardTitle>
                            <CardDescription>
                                Press <strong>Run verification</strong>. For a
                                few hundred members it takes a few seconds.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul className="text-muted-foreground list-disc space-y-1 pl-5 text-sm">
                                {Object.values(checks).map((label) => (
                                    <li key={label}>{label}</li>
                                ))}
                            </ul>
                            <p className="text-muted-foreground mt-3 text-xs">
                                Not re-derived: whether a member qualified for a
                                Booster level, and Monthly Draw eligibility and
                                winners (only the payouts created are checked).
                            </p>
                        </CardContent>
                    </Card>
                )}

                {report && (
                    <>
                        <div
                            role="status"
                            className={`flex items-center gap-3 rounded-lg border p-4 ${
                                report.errors > 0
                                    ? 'border-destructive/40 bg-destructive/10 text-destructive'
                                    : 'border-green-600/30 bg-green-500/10 text-green-700 dark:text-green-400'
                            }`}
                        >
                            {report.errors > 0 ? (
                                <XCircle className="size-6 shrink-0" />
                            ) : (
                                <CheckCircle2 className="size-6 shrink-0" />
                            )}
                            <div>
                                <div className="font-semibold">
                                    {report.errors > 0
                                        ? `${report.errors} difference${report.errors === 1 ? '' : 's'} found`
                                        : 'All earnings match their source events'}
                                </div>
                                <div className="text-sm opacity-80">
                                    {report.warnings > 0
                                        ? `${report.warnings} warning${report.warnings === 1 ? '' : 's'} (things that can legitimately differ). `
                                        : ''}
                                    Checked {formatDateTime(report.ran_at)}
                                </div>
                            </div>
                        </div>

                        {Object.entries(report.checks).map(([key, check]) => (
                            <CheckCard key={key} check={check} />
                        ))}
                    </>
                )}
            </div>
        </>
    );
}

function CheckCard({ check }: { check: CheckResult }) {
    const [open, setOpen] = useState(check.errors > 0);
    const hidden = check.errors + check.warnings - check.findings.length;
    const hasFindings = check.findings.length > 0;

    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between gap-3 space-y-0">
                <div className="min-w-0">
                    <CardTitle className="text-base">{check.label}</CardTitle>
                    <CardDescription>
                        {check.checked.toLocaleString('en-IN')} checked
                    </CardDescription>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    {check.errors > 0 ? (
                        <Badge variant="destructive">
                            {check.errors} error{check.errors === 1 ? '' : 's'}
                        </Badge>
                    ) : (
                        <Badge variant="success">Passed</Badge>
                    )}
                    {check.warnings > 0 && (
                        <Badge variant="secondary">
                            {check.warnings} warning
                            {check.warnings === 1 ? '' : 's'}
                        </Badge>
                    )}
                    {hasFindings && (
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={open ? 'Hide details' : 'Show details'}
                            aria-expanded={open}
                            onClick={() => setOpen(!open)}
                        >
                            <ChevronDown
                                className={`size-4 transition-transform ${open ? 'rotate-180' : ''}`}
                            />
                        </Button>
                    )}
                </div>
            </CardHeader>

            {hasFindings && open && (
                <CardContent className="p-0">
                    <ul className="divide-y border-t">
                        {check.findings.map((finding, i) => (
                            <li
                                key={i}
                                className="flex flex-col gap-1 px-4 py-3 text-sm sm:flex-row sm:gap-4"
                            >
                                <div className="flex shrink-0 items-start gap-2 sm:w-56">
                                    <Badge
                                        variant={
                                            finding.severity === 'error'
                                                ? 'destructive'
                                                : 'secondary'
                                        }
                                    >
                                        {finding.severity}
                                    </Badge>
                                    <span className="font-mono text-xs break-words">
                                        {finding.ref}
                                    </span>
                                </div>
                                <p className="min-w-0 flex-1 break-words">
                                    {finding.message}
                                </p>
                            </li>
                        ))}
                    </ul>
                    {hidden > 0 && (
                        <p className="text-muted-foreground border-t px-4 py-3 text-xs">
                            … and {hidden} more not shown. Run{' '}
                            <code>php artisan earnings:verify --limit=500</code>{' '}
                            for the full list.
                        </p>
                    )}
                </CardContent>
            )}
        </Card>
    );
}

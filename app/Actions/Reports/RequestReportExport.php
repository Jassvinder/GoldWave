<?php

namespace App\Actions\Reports;

use App\Jobs\ProcessReportExport;
use App\Models\ReportExport;
use App\Models\User;

/**
 * T-018 — creates the tracking row and dispatches the queued job
 * (`DOMAIN_LOGIC.md` §21's T-018 pre-coding entry: every export is queued
 * uniformly, never generated synchronously).
 */
class RequestReportExport
{
    /** @param  array<string, mixed>  $filters */
    public function __invoke(string $reportType, string $format, array $filters, User $operator): ReportExport
    {
        $export = ReportExport::create([
            'requested_by' => $operator->id,
            'report_type' => $reportType,
            'format' => $format,
            'filters' => $filters,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        ProcessReportExport::dispatch($export);

        return $export;
    }
}

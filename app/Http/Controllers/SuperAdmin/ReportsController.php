<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Reports\RequestReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\RequestReportExportRequest;
use App\Models\ReportExport;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * INSTRUCTIONS.md "Reports" (Super Admin — "Filter/export across all
 * financial/business modules"), T-018. A new, separate, Super-Admin-only
 * Reports Center — does not touch M17 (Member's own small/synchronous
 * reports) or A06 (Admin's own store-scoped reports), per
 * `DOMAIN_LOGIC.md` §21's T-018 pre-coding entry.
 */
class ReportsController extends Controller
{
    private const REPORT_TYPES = [
        'membership' => 'Membership',
        'emi' => 'EMI',
        'level-income' => 'Level Income',
        'pair-reward' => 'Pair/Reward',
        'draw' => 'Draw',
        'booster' => 'Booster',
        'payment-in' => 'Payment In',
        'payment-out' => 'Payment Out',
        'wallet-ledger' => 'Wallet/Ledger',
        'store-sales' => 'Store Sales',
        'store-distribution' => 'Store Distribution',
    ];

    public function index(Request $request): Response
    {
        $exports = ReportExport::where('requested_by', $request->user()->id)
            ->orderByDesc('requested_at')
            ->limit(50)
            ->get()
            ->map(fn (ReportExport $e) => [
                'id' => $e->id,
                'report_type' => self::REPORT_TYPES[$e->report_type] ?? $e->report_type,
                'format' => $e->format,
                'status' => $e->status,
                'row_count' => $e->row_count,
                'error_message' => $e->error_message,
                'requested_at' => Dates::date($e->requested_at),
            ]);

        return Inertia::render('super-admin/reports', [
            'report_types' => self::REPORT_TYPES,
            'exports' => $exports,
        ]);
    }

    public function store(RequestReportExportRequest $request, RequestReportExport $action): RedirectResponse
    {
        $filters = array_filter([
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'customer_id' => $request->input('customer_id'),
            'store_id' => $request->input('store_id'),
        ], fn ($value) => $value !== null && $value !== '');

        $action(
            $request->string('report_type')->toString(),
            $request->string('format')->toString(),
            $filters,
            $request->user(),
        );

        return redirect()->route('super-admin.reports.index')->with('status', 'Report export queued.');
    }

    public function download(ReportExport $export): StreamedResponse|HttpResponse
    {
        $fileExists = $export->file_path !== null && Storage::disk('local')->exists($export->file_path);

        abort_if($export->status !== 'ready' || ! $fileExists, 404, 'This report is not ready for download.');

        $label = self::REPORT_TYPES[$export->report_type] ?? $export->report_type;
        $filename = str($label)->slug()->toString()."-{$export->id}.{$export->format}";

        return Storage::disk('local')->download($export->file_path, $filename);
    }
}

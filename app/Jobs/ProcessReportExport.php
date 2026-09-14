<?php

namespace App\Jobs;

use App\Exports\ReportExportSheet;
use App\Models\ReportExport;
use App\Services\ReportCatalog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Throwable;

/**
 * DOMAIN_LOGIC.md §21's T-018 pre-coding entry — resolves the report's rows
 * via `ReportCatalog`, writes the file in the requested format to the
 * private `local` disk, and closes out the `report_exports` row. Never
 * left `processing` on failure — always resolves to `ready` or `failed`.
 */
class ProcessReportExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly ReportExport $export) {}

    public function handle(ReportCatalog $catalog): void
    {
        $this->export->update(['status' => 'processing']);

        try {
            $method = $this->reportMethod($this->export->report_type);
            /** @var array{header: array<int, string>, rows: LazyCollection<int, mixed>} $result */
            $result = $catalog->{$method}($this->export->filters ?? []);

            $rows = collect();
            $rowCount = 0;

            foreach ($result['rows'] as $row) {
                $rows->push($row);
                $rowCount++;
            }

            $path = "report-exports/{$this->export->id}.{$this->export->format}";

            match ($this->export->format) {
                'csv' => $this->writeCsv($path, $result['header'], $rows),
                'xlsx' => Excel::store(new ReportExportSheet($result['header'], $rows), $path, 'local'),
                'pdf' => Storage::disk('local')->put($path, Pdf::loadView('reports.export-pdf', [
                    'title' => $this->export->report_type,
                    'header' => $result['header'],
                    'rows' => $rows,
                ])->output()),
            };

            $this->export->update([
                'status' => 'ready',
                'file_path' => $path,
                'row_count' => $rowCount,
                'completed_at' => now(),
            ]);
        } catch (Throwable $e) {
            $this->export->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }

    private function reportMethod(string $reportType): string
    {
        $map = [
            'membership' => 'membership',
            'emi' => 'emi',
            'level-income' => 'levelIncome',
            'pair-reward' => 'pairReward',
            'draw' => 'draw',
            'booster' => 'booster',
            'payment-in' => 'paymentIn',
            'payment-out' => 'paymentOut',
            'wallet-ledger' => 'walletLedger',
            'store-sales' => 'storeSales',
            'store-distribution' => 'storeDistribution',
        ];

        if (! array_key_exists($reportType, $map)) {
            throw new InvalidArgumentException("Unknown report type: {$reportType}");
        }

        return $map[$reportType];
    }

    /**
     * @param  array<int, string>  $header
     * @param  Collection<int, array<int, mixed>>  $rows
     */
    private function writeCsv(string $path, array $header, Collection $rows): void
    {
        $stream = fopen('php://temp', 'w+');

        if ($stream === false) {
            throw new RuntimeException('Unable to open a temporary stream for CSV generation.');
        }

        fputcsv($stream, $header);

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        Storage::disk('local')->put($path, $content === false ? '' : $content);
    }
}

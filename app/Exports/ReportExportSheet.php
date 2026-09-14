<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * T-018 — a minimal, generic `.xlsx` sheet: header + already-flattened
 * rows, both already resolved by `ReportCatalog` by the time this runs.
 */
class ReportExportSheet implements FromCollection, WithHeadings
{
    /**
     * @param  array<int, string>  $header
     * @param  Collection<int, array<int, mixed>>  $rows
     */
    public function __construct(private readonly array $header, private readonly Collection $rows) {}

    /** @return array<int, string> */
    public function headings(): array
    {
        return $this->header;
    }

    /** @return Collection<int, array<int, mixed>> */
    public function collection(): Collection
    {
        return $this->rows;
    }
}

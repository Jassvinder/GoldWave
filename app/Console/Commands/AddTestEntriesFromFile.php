<?php

namespace App\Console\Commands;

use App\Support\TestEntryRegistrar;
use Illuminate\Console\Command;
use Throwable;

/**
 * Earnings-testing helper (13-10-2026, user-requested): registers many test members from a CSV file, through the same
 * real flow as `test:entries`. Columns: sponsor, side, plan, name (header row optional; plan and name may be empty).
 *
 * Sponsor is an existing Customer ID (e.g. GWL-ROOT, GWL05), PREV (the entry just above), or #n (entry n of this file,
 * counted without the header — its Customer ID is not known until it is added). The whole file is checked first:
 * if any entry is wrong, nothing is added. Results are printed and saved next to the file as <name>-result.csv.
 *
 * Usage: php artisan test:entries-file entries.csv [--plan=E]
 */
class AddTestEntriesFromFile extends Command
{
    private const MAX_ENTRIES = 2000;

    protected $signature = 'test:entries-file {file : path to the CSV file} {--plan=E : plan code for entries whose plan column is empty}';

    protected $description = 'Register test members listed in a CSV file (sponsor, side, plan, name) — not in production.';

    public function handle(TestEntryRegistrar $registrar): int
    {
        if (app()->isProduction()) {
            $this->error('test:entries-file never runs in production.');

            return self::FAILURE;
        }

        $path = (string) $this->argument('file');
        $admin = $registrar->approver();

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("File not found or not readable: {$path}");

            return self::FAILURE;
        }

        if ($admin === null) {
            $this->error('No Super Admin user was found — create one first.');

            return self::FAILURE;
        }

        $entries = $this->readEntries($path);
        $errors = $this->validate($entries, $registrar);

        if ($errors !== []) {
            $this->error('Nothing was added. Fix these lines and run again:');
            foreach ($errors as $error) {
                $this->line("  - {$error}");
            }

            return self::FAILURE;
        }

        $this->info('File checked: '.count($entries).' entries. Adding them now…');

        $results = [];
        $exitCode = self::SUCCESS;

        foreach ($entries as $index => $entry) {
            $sponsor = $this->sponsorFor($entry['sponsor'], $index, $results);

            try {
                $member = $registrar->register($sponsor, $entry['side'], (int) $entry['plan_id'], $admin, $entry['name']);
            } catch (Throwable $e) {
                $this->error("Entry {$entry['no']} (line {$entry['line']}) failed: {$e->getMessage()}");
                $this->warn('Entries before it were added; it and the entries after it were not.');
                $exitCode = self::FAILURE;
                break;
            }

            $results[$index] = [
                'entry' => $entry['no'],
                'customer_id' => (string) $member->customer_id,
                'name' => (string) $member->user?->name,
                'sponsor' => $sponsor,
                'placed_under' => $member->placementParent->customer_id ?? '—',
                'side' => (string) $member->placement_side,
                'plan' => $entry['plan'],
            ];

            $this->line(vsprintf('#%d %s | %s | sponsor %s | under %s %s | plan %s', array_values($results[$index])));
        }

        $this->writeResults($path, $results);

        if ($exitCode === self::SUCCESS) {
            $this->info(count($results).' entries added.');
        }

        return $exitCode;
    }

    /**
     * @return list<array{no: int, line: int, sponsor: string, side: string, plan: string, plan_id: int|null, name: string|null}>
     */
    private function readEntries(string $path): array
    {
        $handle = fopen($path, 'r');
        $entries = [];
        $line = 0;

        while ($handle !== false && ($row = fgetcsv($handle, escape: '\\')) !== false) {
            $line++;
            $cells = array_map(fn ($cell) => trim((string) $cell), $row);
            $cells[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cells[0]) ?? $cells[0]; // Excel's UTF-8 BOM

            if (implode('', $cells) === '' || ($entries === [] && strtolower($cells[0]) === 'sponsor')) {
                continue; // blank line or the header row
            }

            $plan = strtoupper($cells[2] ?? '') ?: strtoupper((string) $this->option('plan'));

            $entries[] = [
                'no' => count($entries) + 1,
                'line' => $line,
                'sponsor' => strtoupper($cells[0]),
                'side' => strtolower($cells[1] ?? ''),
                'plan' => $plan,
                'plan_id' => null,
                'name' => ($cells[3] ?? '') !== '' ? $cells[3] : null,
            ];
        }

        if ($handle !== false) {
            fclose($handle);
        }

        return $entries;
    }

    /**
     * Checks every entry before anything is added, and fills in each entry's plan id.
     *
     * @param  list<array{no: int, line: int, sponsor: string, side: string, plan: string, plan_id: int|null, name: string|null}>  $entries
     * @return list<string>
     */
    private function validate(array &$entries, TestEntryRegistrar $registrar): array
    {
        if ($entries === []) {
            return ['The file has no entries.'];
        }

        if (count($entries) > self::MAX_ENTRIES) {
            return ['The file has '.count($entries).' entries; the limit is '.self::MAX_ENTRIES.' per run — split it into several files.'];
        }

        $errors = [];
        $planIds = [];

        foreach ($entries as $index => $entry) {
            $where = "Entry {$entry['no']} (line {$entry['line']})";

            if (! in_array($entry['side'], ['left', 'right'], true)) {
                $errors[] = "{$where}: side must be left or right, not \"{$entry['side']}\".";
            }

            $planIds[$entry['plan']] ??= $registrar->planId($entry['plan']);
            if ($planIds[$entry['plan']] === null) {
                $errors[] = "{$where}: plan \"{$entry['plan']}\" does not exist (use A–F).";
            }
            $entries[$index]['plan_id'] = $planIds[$entry['plan']];

            $sponsor = $entry['sponsor'];
            if ($sponsor === '') {
                $errors[] = "{$where}: sponsor is empty.";
            } elseif ($sponsor === 'PREV') {
                if ($index === 0) {
                    $errors[] = "{$where}: PREV has no entry above it.";
                }
            } elseif (str_starts_with($sponsor, '#')) {
                $ref = (int) substr($sponsor, 1);
                if ((string) $ref !== substr($sponsor, 1) || $ref < 1 || $ref >= $entry['no']) {
                    $errors[] = "{$where}: {$sponsor} must point to an entry above this one (#1 to #".($entry['no'] - 1).').';
                }
            } elseif (! $registrar->isActiveSponsor($sponsor)) {
                $errors[] = "{$where}: sponsor {$sponsor} is not an active member.";
            }
        }

        return $errors;
    }

    /** @param  array<int, array<string, string|int>>  $results */
    private function sponsorFor(string $sponsor, int $index, array $results): string
    {
        return match (true) {
            $sponsor === 'PREV' => (string) $results[$index - 1]['customer_id'],
            str_starts_with($sponsor, '#') => (string) $results[(int) substr($sponsor, 1) - 1]['customer_id'],
            default => $sponsor,
        };
    }

    /** @param  array<int, array<string, string|int>>  $results */
    private function writeResults(string $path, array $results): void
    {
        if ($results === []) {
            return;
        }

        $target = preg_replace('/\.csv$/i', '', $path).'-result.csv';
        $handle = @fopen($target, 'w');

        if ($handle === false) {
            $this->warn("Could not write {$target} — the results are only in the output above.");

            return;
        }

        fputcsv($handle, array_keys(reset($results)), escape: '\\');
        foreach ($results as $row) {
            fputcsv($handle, $row, escape: '\\');
        }
        fclose($handle);

        $this->info("Results saved to {$target}");
    }
}

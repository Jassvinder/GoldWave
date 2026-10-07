<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * T-206 (02-10-2026) — bank proof documents and GPay/UPI payment screenshots move from the public disk (reachable by
 * URL without login) to the private `local` disk. The stored relative paths stay the same, so no column changes; only
 * the files move. Safe to re-run: a file already on the private disk, or missing from the public one, is skipped.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->paths() as $path) {
            $this->move($path, from: 'public', to: 'local');
        }
    }

    public function down(): void
    {
        foreach ($this->paths() as $path) {
            $this->move($path, from: 'local', to: 'public');
        }
    }

    /** @return list<string> */
    private function paths(): array
    {
        $paths = DB::table('member_bank_details')->whereNotNull('proof_document_path')->pluck('proof_document_path')
            ->merge(DB::table('payments')->whereNotNull('upi_screenshot_path')->pluck('upi_screenshot_path'));

        $unique = [];

        foreach ($paths as $path) {
            if (is_string($path) && ! in_array($path, $unique, true)) {
                $unique[] = $path;
            }
        }

        return $unique;
    }

    private function move(string $path, string $from, string $to): void
    {
        $source = Storage::disk($from);
        $target = Storage::disk($to);

        if (! $source->exists($path) || $target->exists($path)) {
            return;
        }

        $stream = $source->readStream($path);

        if ($stream !== null && $target->writeStream($path, $stream)) {
            $source->delete($path);
        }

        if (is_resource($stream)) {
            fclose($stream);
        }
    }
};

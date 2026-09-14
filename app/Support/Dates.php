<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * ARCHITECTURE.md's Larastan/Carbon tooling note (T-005): Larastan 3.11
 * doesn't reliably infer a `datetime`/`date`-cast attribute as `Carbon` on
 * this Laravel version, even via the modern `casts()` method style — calling
 * `?->toDateString()` directly on one is flagged as "cannot call method on
 * string". `Carbon::parse(...)` sidesteps it; this helper centralizes that
 * workaround for read-only Inertia props, where it would otherwise repeat at
 * nearly every date field across every Member Portal controller (T-015).
 */
class Dates
{
    public static function date(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->toDateString() : null;
    }

    /**
     * DD-MM-YYYY, the project's hard Indian-date-format requirement (see
     * AGENTS.md "Conventions"), for dates rendered directly into a
     * server-generated file (CSV/XLSX/PDF report export, T-018) where no
     * frontend `formatDate()` pass ever runs.
     */
    public static function display(mixed $value): string
    {
        return $value ? Carbon::parse($value)->format('d-m-Y') : '—';
    }
}

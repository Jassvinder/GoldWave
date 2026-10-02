<?php

namespace App\Actions\Billing;

use App\Models\HallmarkEntry;
use Illuminate\Validation\ValidationException;

/**
 * T-171 (28-09-2026, user decision — DOMAIN_LOGIC.md §16.2 T-171 note): a bill may mark its pieces as hallmarked —
 * **one HUID number and one hallmark charge per piece** (quantity 2 → 2 HUIDs), entered by hand. This validates that
 * list and stores it, returning the hallmark total to add before GST. An empty list means "not hallmarked".
 */
class RecordHallmarks
{
    /**
     * @param  list<array{huid: string, charge: float|int|string}>  $pieces
     */
    public static function validate(array $pieces, int $quantity): float
    {
        if ($pieces === []) {
            return 0.0;
        }

        if (count($pieces) !== $quantity) {
            throw ValidationException::withMessages([
                'hallmarks' => "Enter one HUID and hallmark charge for each of the {$quantity} piece(s).",
            ]);
        }

        $huids = [];

        foreach ($pieces as $i => $piece) {
            $huid = strtoupper(trim((string) $piece['huid']));

            if (! preg_match('/^[A-Z0-9]{6,8}$/', $huid)) {
                throw ValidationException::withMessages(["hallmarks.{$i}.huid" => 'A HUID is 6–8 letters or digits.']);
            }

            if (in_array($huid, $huids, true)) {
                throw ValidationException::withMessages(["hallmarks.{$i}.huid" => 'Each piece has its own HUID — this one is repeated.']);
            }

            if (! is_numeric($piece['charge']) || (float) $piece['charge'] < 0) {
                throw ValidationException::withMessages(["hallmarks.{$i}.charge" => 'Enter the hallmark charge for this piece.']);
            }

            $huids[] = $huid;
        }

        return round(array_sum(array_map(fn (array $piece): float => (float) $piece['charge'], $pieces)), 2);
    }

    /**
     * @param  list<array{huid: string, charge: float|int|string}>  $pieces
     * @param  array{store_sale_id?: int, company_delivery_id?: int}  $owner
     */
    public static function store(array $pieces, array $owner): void
    {
        foreach ($pieces as $i => $piece) {
            HallmarkEntry::create($owner + [
                'piece_no' => $i + 1,
                'huid' => strtoupper(trim((string) $piece['huid'])),
                'charge' => round((float) $piece['charge'], 2),
            ]);
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Support\TestEntryRegistrar;
use Illuminate\Console\Command;
use Throwable;

/**
 * Earnings-testing helper (13-10-2026, user-requested): registers --count test members in one run, all sponsored by
 * --under on the same --side, through the same real flow as `test:entries`. Placement follows the normal rule
 * (straight down that side), so the entries form one long chain under the sponsor's left or right leg.
 *
 * Usage: php artisan test:entries-bulk --under=GWL-ROOT --side=left --count=50 [--plan=E]
 */
class AddBulkTestEntries extends Command
{
    private const MAX_COUNT = 1000;

    protected $signature = 'test:entries-bulk
        {--under= : sponsor Customer ID for every entry}
        {--side= : left or right}
        {--count= : how many entries to add (1–1000)}
        {--plan=E : membership plan code}';

    protected $description = 'Register many test members under one sponsor on one side — not in production.';

    public function handle(TestEntryRegistrar $registrar): int
    {
        if (app()->isProduction()) {
            $this->error('test:entries-bulk never runs in production.');

            return self::FAILURE;
        }

        $sponsor = strtoupper(trim((string) $this->option('under')));
        $side = strtolower(trim((string) $this->option('side')));
        $count = filter_var($this->option('count'), FILTER_VALIDATE_INT);
        $planId = $registrar->planId((string) $this->option('plan'));
        $admin = $registrar->approver();

        $errors = array_filter([
            $sponsor === '' || ! $registrar->isActiveSponsor($sponsor) ? "--under: \"{$sponsor}\" is not an active member." : null,
            ! in_array($side, ['left', 'right'], true) ? '--side must be left or right.' : null,
            $count === false || $count < 1 || $count > self::MAX_COUNT ? '--count must be a number from 1 to '.self::MAX_COUNT.'.' : null,
            $planId === null ? 'Plan '.$this->option('plan').' does not exist (use A–F).' : null,
            $admin === null ? 'No Super Admin user was found — create one first.' : null,
        ]);

        if ($errors !== [] || $planId === null || $admin === null || $count === false) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $added = [];

        for ($i = 1; $i <= $count; $i++) {
            try {
                $member = $registrar->register($sponsor, $side, $planId, $admin);
            } catch (Throwable $e) {
                $this->error("Entry {$i} failed: {$e->getMessage()}");
                $this->warn(count($added).' entries were added before it.');

                return self::FAILURE;
            }

            $added[] = (string) $member->customer_id;
            $this->line(sprintf(
                '%d/%d %s | sponsor %s | under %s %s',
                $i,
                $count,
                $member->customer_id,
                $sponsor,
                $member->placementParent->customer_id ?? '—',
                $member->placement_side,
            ));
        }

        $this->info(count($added)." entries added under {$sponsor} ({$side}): {$added[0]} to ".end($added).'.');

        return self::SUCCESS;
    }
}

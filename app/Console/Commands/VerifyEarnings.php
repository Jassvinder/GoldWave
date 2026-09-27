<?php

namespace App\Console\Commands;

use App\Services\EarningsVerifier;
use Illuminate\Console\Command;

/**
 * Re-computes every stored earning from its source event and compares it with what the compensation Actions wrote (see
 * `EarningsVerifier`). Read-only. Exit code 1 when any real difference is found, so it can also gate a deployment / CI job.
 */
class VerifyEarnings extends Command
{
    protected $signature = 'earnings:verify
        {--only=* : limit to checks: level, purchase, store, pair, booster, ledger, wallet}
        {--limit=25 : how many findings to print per check}';

    protected $description = 'Independently re-compute all earnings (Level, Purchase/Repurchase, Store, Pair/Reward, Booster) and compare them with what is stored.';

    public function handle(EarningsVerifier $verifier): int
    {
        $only = array_values(array_filter((array) $this->option('only')));
        $unknown = array_diff($only, array_keys(EarningsVerifier::CHECKS));

        if ($unknown !== []) {
            $this->error('Unknown check(s): '.implode(', ', $unknown).'. Valid: '.implode(', ', array_keys(EarningsVerifier::CHECKS)));

            return self::INVALID;
        }

        $report = $verifier->run($only, (int) $this->option('limit'));

        foreach ($report['checks'] as $key => $check) {
            $status = $check['errors'] > 0 ? '<fg=red>FAIL</>' : ($check['warnings'] > 0 ? '<fg=yellow>WARN</>' : '<fg=green>PASS</>');
            $this->line(sprintf('%s  %-32s checked %-6d errors %-4d warnings %d', $status, $check['label'], $check['checked'], $check['errors'], $check['warnings']));

            foreach ($check['findings'] as $finding) {
                $tag = $finding['severity'] === 'error' ? '<fg=red>  ✗</>' : '<fg=yellow>  !</>';
                $this->line("{$tag} [{$finding['ref']}] {$finding['message']}");
            }

            $hidden = $check['errors'] + $check['warnings'] - count($check['findings']);

            if ($hidden > 0) {
                $this->line("     … and {$hidden} more (raise --limit to see them)");
            }
        }

        $this->newLine();

        if ($report['errors'] > 0) {
            $this->error("{$report['errors']} difference(s) found, {$report['warnings']} warning(s).");

            return self::FAILURE;
        }

        $this->info("All earnings match their source events ({$report['warnings']} warning(s)).");

        return self::SUCCESS;
    }
}

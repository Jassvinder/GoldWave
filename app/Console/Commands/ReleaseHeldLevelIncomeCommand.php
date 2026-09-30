<?php

namespace App\Console\Commands;

use App\Actions\Compensation\ReleaseHeldLevelIncome;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

/**
 * T-186 (DOMAIN_LOGIC.md §6) — releases, right now, every held Level Income row whose beneficiary already has the
 * level's qualified directs. The same code runs on its own when a direct qualifies or a rule version is published;
 * this command is for after the T-186 migration (old lapsed rows became held) and for manual checks. Idempotent.
 */
class ReleaseHeldLevelIncomeCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'level-income:release-held {--force : run in production without asking}';

    protected $description = 'Release held Level Income for every member who now has enough qualified directs.';

    public function handle(ReleaseHeldLevelIncome $release): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $released = $release->forAll();
        $this->info('Held Level Income released for '.count($released).' member(s).');

        foreach ($released as $customerId => $amount) {
            $this->line(sprintf('  %s  ₹%s', $customerId, number_format($amount, 2)));
        }

        return self::SUCCESS;
    }
}

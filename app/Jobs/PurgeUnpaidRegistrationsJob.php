<?php

namespace App\Jobs;

use App\Actions\Registration\PurgeUnpaidRegistrations;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** T-243 (DOMAIN_LOGIC.md §2.2a) — daily: delete registrations still unpaid one month later. Idempotent. */
class PurgeUnpaidRegistrationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(PurgeUnpaidRegistrations $purge): void
    {
        $purge();
    }
}

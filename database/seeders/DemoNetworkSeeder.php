<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Dev/demo data only — NOT part of `DatabaseSeeder` (tests call `$this->seed()`, and this takes minutes). Registers ~500
 * members through the real Registration/Payment Actions, plus 4 demo stores with inventory, sales and buybacks, dummy
 * entries, draws and pair milestones (see `App\Console\Commands\DemoSeedNetwork`).
 *
 * Fresh dev database from zero:  php artisan migrate:fresh --seed && php artisan db:seed --class=DemoNetworkSeeder
 */
class DemoNetworkSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('demo:seed-network', ['count' => 500], $this->command->getOutput());
    }
}

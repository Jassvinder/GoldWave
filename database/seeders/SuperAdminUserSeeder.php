<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Dev/test convenience only — not a DOMAIN_LOGIC.md requirement. Super
 * Admin/Admin account creation (S02) is T-017 territory; this just seeds one
 * usable Super Admin login locally so DATABASE_SCHEMA.md rows that require a
 * `created_by`/`published_by` user (metal_rates, rule_versions) have one, and
 * so Super Admin routes (e.g. cash-payment approval) are reachable in dev
 * without waiting for T-017.
 */
class SuperAdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        User::updateOrCreate(
            ['email' => 'superadmin@goldwave.test'],
            [
                'name' => 'GoldWave Super Admin',
                'mobile' => '9000000000',
                'password' => 'password',
                'role' => 'super_admin',
                'email_verified_at' => now(),
            ],
        );
    }
}

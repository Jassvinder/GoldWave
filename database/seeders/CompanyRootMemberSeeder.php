<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * DOMAIN_LOGIC.md §21 T-013 pre-coding pass (user-confirmed): a single fixed
 * "Company" root Member anchors every daily dummy batch's Right-side
 * placement, and every dummy entry's `sponsor_id` — unifying §14's "Remain
 * the company's Direct relationship" (Sponsor/Direct sense) with the Binary
 * Position placement anchor in one entity. `is_company_dummy=true` with
 * `dummy_status` permanently null means the same exclusion guard
 * (`Actions/Compensation/CreatePairEntries`/`EvaluateBoosterQualification`)
 * that skips unassigned dummy ancestors also skips this root — it must
 * never itself become a paid compensation beneficiary. Uses a reserved,
 * out-of-sequence Customer ID rather than `CustomerIdGenerator`, so it never
 * consumes a slot from the real GWL0N numbering.
 *
 * 29-09-2026 (user request): the root is always present on a fresh database with a login user of its own. It stays a
 * non-earning company root. 15-10-2026 (user request): named "Shri Shyam", and identified only by its fixed Customer ID
 * `GWL-ROOT` — never by an email or mobile, which belong to people and can move to a real member. The seeder only
 * creates a missing root; it never touches an existing root or its login (email, mobile, password are set on the server).
 */
class CompanyRootMemberSeeder extends Seeder
{
    public const CUSTOMER_ID = 'GWL-ROOT';

    public function run(): void
    {
        if (Member::where('customer_id', self::CUSTOMER_ID)->exists()) {
            return;
        }

        $user = User::create([
            'name' => 'Shri Shyam',
            // `users.email` is required and unique; this placeholder is no one's inbox. Set a real one on the server.
            'email' => 'gwl-root@goldwave.invalid',
            'role' => 'member',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        Member::create([
            'user_id' => $user->id,
            'customer_id' => self::CUSTOMER_ID,
            'status' => 'active',
            'activated_at' => now(),
            'is_company_dummy' => true,
            'is_company_root' => true,
            'placeholder_name' => 'Shri Shyam',
        ]);
    }
}

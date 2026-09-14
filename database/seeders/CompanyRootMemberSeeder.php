<?php

namespace Database\Seeders;

use App\Models\Member;
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
 */
class CompanyRootMemberSeeder extends Seeder
{
    public function run(): void
    {
        Member::updateOrCreate(
            ['is_company_root' => true],
            [
                'customer_id' => 'GWL-ROOT',
                'status' => 'active',
                'is_company_dummy' => true,
                'placeholder_name' => 'GoldWave Company (Root)',
            ],
        );
    }
}

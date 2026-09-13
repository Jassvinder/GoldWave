<?php

namespace App\Actions\Registration;

use App\Models\Member;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §2.1 point 1: the invitation/sponsor code is the sponsor's
 * own Customer ID (no separate invite-code concept exists anywhere in
 * DATABASE_SCHEMA.md). An invalid code and a code belonging to a
 * currently-inactive sponsor are treated identically — both block
 * registration with the same error, re-evaluated fresh on every attempt
 * (never cached), per the sponsor-must-be-active rule resolved 12-09-2026.
 */
class ValidateSponsorCode
{
    public function __invoke(string $code): Member
    {
        $sponsor = Member::where('customer_id', $code)->first();

        if (! $sponsor || $sponsor->status !== 'active') {
            throw ValidationException::withMessages([
                'sponsor_code' => 'Invalid sponsor code.',
            ]);
        }

        return $sponsor;
    }
}

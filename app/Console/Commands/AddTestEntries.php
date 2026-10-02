<?php

namespace App\Console\Commands;

use App\Actions\Payments\ApproveCashPayment;
use App\Actions\Registration\RegisterMember;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Earnings-testing helper (30-09-2026): registers test members through the real flow (RegisterMember + cash approval),
 * so every income runs exactly as for a real joining. Each member is named after the Customer ID it actually got
 * ("Test Member 17" = GWL17). The name is never computed from a member count: company placeholder entries also get
 * members rows and used to shift every later name by one. Development only.
 *
 * Usage: php artisan test:entries "GWL01:left,PREV:right" [--plan=E]
 * Each item is SPONSOR:side; sponsor PREV = the entry added just before it in the same run.
 */
class AddTestEntries extends Command
{
    protected $signature = 'test:entries {entries : comma-separated SPONSOR:side items, e.g. "GWL01:left,PREV:right"} {--plan=E : membership plan code}';

    protected $description = 'Register test members (cash, approved) named after their own Customer ID — development only.';

    public function handle(RegisterMember $register, ApproveCashPayment $approve): int
    {
        if (app()->isProduction()) {
            $this->error('test:entries never runs in production.');

            return self::FAILURE;
        }

        $planId = MembershipPlan::where('code', $this->option('plan'))->value('id');
        $admin = User::where('role', 'super_admin')->first();

        if ($planId === null || $admin === null) {
            $this->error('Plan '.$this->option('plan').' or a Super Admin user was not found.');

            return self::FAILURE;
        }

        $previous = null;

        foreach (explode(',', (string) $this->argument('entries')) as $item) {
            [$sponsor, $side] = array_map('trim', explode(':', $item)) + [1 => ''];
            $sponsor = strtoupper($sponsor) === 'PREV' ? $previous : $sponsor;

            if ($sponsor === null || ! in_array($side, ['left', 'right'], true)) {
                $this->error("Invalid item \"{$item}\" — use SPONSOR:left or SPONSOR:right.");

                return self::FAILURE;
            }

            $member = DB::transaction(function () use ($register, $approve, $sponsor, $side, $planId, $admin): Member {
                $token = uniqid();
                $member = $register([
                    'sponsor_code' => $sponsor,
                    'placement_side' => $side,
                    'gender' => 'male',
                    'name' => 'Test Member',
                    'email' => "test-{$token}@goldwave.test",
                    'mobile' => '97'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
                    'membership_plan_id' => $planId,
                    'payment_mode' => 'cash',
                ]);

                $approve($member->payments()->where('type', 'registration')->firstOrFail(), $admin);

                // The Customer ID is issued on approval — name and email follow the ID the member actually received.
                $member = $member->fresh(['user', 'placementParent']) ?? $member;
                $number = self::numberOf((string) $member->customer_id);
                $member->user?->update(['name' => "Test Member {$number}", 'email' => "member{$number}-{$token}@goldwave.test"]);

                return $member;
            });

            $this->line(sprintf(
                '%s | %s | sponsor %s | under %s %s',
                $member->customer_id,
                $member->user?->name,
                $sponsor,
                $member->placementParent->customer_id ?? '—',
                $member->placement_side,
            ));

            $previous = $member->customer_id;
        }

        return self::SUCCESS;
    }

    /** "GWL17" → "17", "GWL01" → "1" (the existing "Test Member 1" style). */
    public static function numberOf(string $customerId): string
    {
        $digits = preg_replace('/\D/', '', $customerId);

        return $digits === '' || $digits === null ? $customerId : (string) (int) $digits;
    }
}

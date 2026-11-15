<?php

namespace App\Actions\Registration;

use App\Models\EmiSchedule;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * T-243 (DOMAIN_LOGIC.md §2.2a) — a registration that is still unpaid one calendar month after it was made is deleted
 * completely (user, member, pending payment, EMI schedule, GPay/UPI proof file), which also frees its place in the
 * binary tree. A Customer ID is never given before activation, so none is lost. It is kept when:
 * - a GPay/UPI payment with a Ref ID is waiting for approval (the member says they paid; staff must check it), or
 * - anyone is placed or sponsored under it (deleting it would cut them off the tree).
 */
class PurgeUnpaidRegistrations
{
    public const PENDING_STATUSES = ['draft', 'payment_pending'];

    /** @return int how many registrations were deleted */
    public function __invoke(): int
    {
        $deleted = 0;

        Member::query()
            ->whereIn('status', self::PENDING_STATUSES)
            ->where('created_at', '<=', self::cutoff())
            ->where('is_company_dummy', false)
            ->where('is_company_root', false)
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $memberId) use (&$deleted) {
                try {
                    if ($this->purge($memberId)) {
                        $deleted++;
                    }
                } catch (Throwable $e) {
                    // One bad row never stops the others; it is retried on the next run.
                    Log::warning('Unpaid registration could not be deleted', ['member_id' => $memberId, 'error' => $e->getMessage()]);
                }
            });

        return $deleted;
    }

    /** One calendar month ago, without Carbon's overflow (31-10 → 30-09, not 01-10). */
    public static function cutoff(): CarbonInterface
    {
        return now()->subMonthNoOverflow();
    }

    /** Whether this member must be kept even though their registration is unpaid. */
    public static function keepReason(Member $member): ?string
    {
        $hasUpiClaim = Payment::where('member_id', $member->id)
            ->where('status', 'pending')
            ->where('mode', 'upi')
            ->whereNotNull('upi_reference')
            ->exists();

        if ($hasUpiClaim) {
            return 'gpay_upi_waiting_for_approval';
        }

        $hasDownline = Member::where('placement_parent_id', $member->id)->orWhere('sponsor_id', $member->id)->exists();

        return $hasDownline ? 'has_members_below' : null;
    }

    private function purge(int $memberId): bool
    {
        $proofFiles = [];

        $done = DB::transaction(function () use ($memberId, &$proofFiles) {
            $member = Member::whereKey($memberId)->lockForUpdate()->first();

            // Re-checked under the lock: it may have been paid or activated since the list was read.
            if ($member === null || ! in_array($member->status, self::PENDING_STATUSES, true)
                || $member->created_at === null || $member->created_at->gt(self::cutoff())
                || self::keepReason($member) !== null) {
                return false;
            }

            $payments = Payment::where('member_id', $member->id)->get();

            if ($payments->contains(fn (Payment $p) => $p->status === 'paid')) {
                return false;
            }

            $proofFiles = $payments->pluck('upi_screenshot_path')->filter()->values()->all();
            $userId = $member->user_id;

            Payment::where('member_id', $member->id)->delete();
            EmiSchedule::where('member_id', $member->id)->delete();
            $member->delete();

            if ($userId !== null) {
                DB::table('notifications')->where('notifiable_type', User::class)->where('notifiable_id', $userId)->delete();
                DB::table('sessions')->where('user_id', $userId)->delete();
                User::whereKey($userId)->delete();
            }

            Log::info('Unpaid registration deleted after one month', ['member_id' => $memberId]);

            return true;
        });

        foreach ($proofFiles as $path) {
            Storage::disk('local')->delete($path);
        }

        return $done;
    }
}

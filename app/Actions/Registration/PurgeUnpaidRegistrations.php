<?php

namespace App\Actions\Registration;

use App\Models\EmiSchedule;
use App\Models\Member;
use App\Models\Payment;
use App\Models\PaymentBatch;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * T-243 (DOMAIN_LOGIC.md §2.2a) — a registration that is still unpaid one calendar month after it was made is deleted
 * completely (member, pending payment, EMI schedule, GPay/UPI proof file, and the user once it holds no ID), which also
 * frees its place in the binary tree. A Customer ID is never given before activation, so none is lost. It is kept when:
 * - a GPay/UPI payment with a Ref ID is waiting for approval (the member says they paid; staff must check it), or
 * - anyone is placed or sponsored under it (deleting it would cut them off the tree).
 *
 * T-251 part 3 (§3.4) — the IDs of one combined payment are one request: deleted together or kept together, and the
 * IDs among themselves do not count as "someone under it". The user is deleted only when no ID of theirs is left, so
 * an unpaid "Add ID" never touches the person's other IDs or their login.
 */
class PurgeUnpaidRegistrations
{
    public const PENDING_STATUSES = ['draft', 'payment_pending'];

    /** @return int how many IDs were deleted */
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
                    $deleted += $this->purge($memberId);
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

    /** Whether this member's request must be kept even though it is unpaid. */
    public static function keepReason(Member $member): ?string
    {
        return self::groupKeepReason(self::group($member));
    }

    /**
     * The IDs of the member's request: every ID of its combined payment, or just the member.
     *
     * @return Collection<int, Member>
     */
    private static function group(Member $member, bool $lock = false): Collection
    {
        $batchId = Payment::where('member_id', $member->id)->where('type', 'registration')->value('payment_batch_id');

        $query = $batchId === null
            ? Member::whereKey($member->id)
            : Member::whereIn('id', Payment::where('payment_batch_id', $batchId)->select('member_id'));

        return ($lock ? $query->lockForUpdate() : $query)->orderBy('id')->get();
    }

    /** @param  Collection<int, Member>  $group */
    private static function groupKeepReason(Collection $group): ?string
    {
        $ids = $group->modelKeys();
        $payments = Payment::whereIn('member_id', $ids)->where('status', 'pending')->get();

        $batchClaim = PaymentBatch::whereIn('id', $payments->pluck('payment_batch_id')->filter())
            ->where('status', 'pending')
            ->where('mode', 'upi')
            ->whereNotNull('upi_reference')
            ->exists();

        if ($batchClaim || $payments->contains(fn (Payment $p) => $p->mode === 'upi' && $p->upi_reference !== null)) {
            return 'gpay_upi_waiting_for_approval';
        }

        $hasDownline = Member::whereNotIn('id', $ids)
            ->where(fn ($query) => $query->whereIn('placement_parent_id', $ids)->orWhereIn('sponsor_id', $ids))
            ->exists();

        return $hasDownline ? 'has_members_below' : null;
    }

    /** @return int how many IDs were deleted (0 when the request is kept or already gone) */
    private function purge(int $memberId): int
    {
        $proofFiles = [];

        $count = DB::transaction(function () use ($memberId, &$proofFiles): int {
            $member = Member::whereKey($memberId)->first();

            if ($member === null) {
                return 0; // Deleted with an earlier ID of the same request.
            }

            $group = self::group($member, lock: true);

            // Re-checked under the lock: it may have been paid or activated since the list was read.
            $stillUnpaid = $group->every(fn (Member $id) => in_array($id->status, self::PENDING_STATUSES, true)
                && $id->created_at !== null && $id->created_at->lte(self::cutoff()));

            if (! $stillUnpaid || self::groupKeepReason($group) !== null) {
                return 0;
            }

            $ids = $group->modelKeys();
            $payments = Payment::whereIn('member_id', $ids)->get();

            if ($payments->contains(fn (Payment $p) => $p->status === 'paid')) {
                return 0;
            }

            $batches = PaymentBatch::whereIn('id', $payments->pluck('payment_batch_id')->filter()->unique())->get();
            $proofFiles = $payments->pluck('upi_screenshot_path')
                ->merge($batches->pluck('upi_screenshot_path'))
                ->filter()->values()->all();
            $userIds = $group->pluck('user_id')->filter()->unique();

            Payment::whereIn('member_id', $ids)->delete();
            PaymentBatch::whereKey($batches->modelKeys())->delete();
            EmiSchedule::whereIn('member_id', $ids)->delete();

            // Deepest first, so no ID is left pointing at a deleted parent within the request.
            foreach ($group->sortByDesc('id') as $id) {
                $id->delete();
            }

            foreach ($userIds as $userId) {
                if (Member::where('user_id', $userId)->exists()) {
                    continue; // The person still holds another ID: their login stays.
                }

                DB::table('notifications')->where('notifiable_type', User::class)->where('notifiable_id', $userId)->delete();
                DB::table('sessions')->where('user_id', $userId)->delete();
                User::whereKey($userId)->delete();
            }

            Log::info('Unpaid registration deleted after one month', ['member_ids' => $ids]);

            return count($ids);
        });

        foreach ($proofFiles as $path) {
            Storage::disk('local')->delete($path);
        }

        return $count;
    }
}

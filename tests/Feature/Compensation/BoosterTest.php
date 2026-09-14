<?php

use App\Actions\Compensation\EvaluateBoosterQualification;
use App\Jobs\ProcessBoosterPayouts;
use App\Models\BoosterPayoutSchedule;
use App\Models\BoosterQualification;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Services\WalletLedgerService;
use Illuminate\Support\Facades\DB;

/**
 * DOMAIN_LOGIC.md §9 (Income Booster), Docs/TEST.md scenario 3 — qualify
 * once, pay 6 months regardless of later drop-off, concurrent multi-level
 * schedules, and the independently-enforced Left/Right split gate.
 */
function boosterMember(string $customerId, ?Member $sponsor = null): Member
{
    $user = User::factory()->create(['role' => 'member']);

    return Member::create([
        'user_id' => $user->id,
        'customer_id' => $customerId,
        'sponsor_id' => $sponsor?->id,
        'status' => 'active',
        'activated_at' => now(),
    ]);
}

function placeChild(Member $parent, string $side, string $customerId): Member
{
    return placeChildById($parent->id, $side, $customerId);
}

function placeChildById(int $parentId, string $side, string $customerId): Member
{
    return Member::create([
        'customer_id' => $customerId,
        'placement_parent_id' => $parentId,
        'placement_side' => $side,
        'status' => 'active',
    ]);
}

/**
 * A `(placement_parent_id, placement_side)` unique constraint means a real
 * binary node can only have one Left and one Right child — so filler
 * members must form a genuine chain (each the previous one's single child
 * on this side), not a flat star. IDs are pre-computed to bulk-insert the
 * whole chain in a few queries instead of one row at a time.
 */
/**
 * A `(placement_parent_id, placement_side)` unique constraint means a real
 * binary node can only have one Left and one Right child — so filler
 * members must form a genuine chain (each the previous one's single child
 * on this side), not a flat star sharing one parent. IDs are pre-computed
 * to bulk-insert the whole chain in a few queries instead of one row at a
 * time. Returns the new leaf id, so a caller can extend the chain further
 * later (growing an already-built team, e.g. crossing a higher Booster
 * level) or place a "trigger" registration underneath it.
 */
function extendChain(int $parentId, string $side, int $additionalCount, string $prefix): int
{
    if ($additionalCount <= 0) {
        return $parentId;
    }

    $now = now();
    $startId = (int) DB::table('members')->max('id') + 1;
    $rows = [];

    for ($i = 1; $i <= $additionalCount; $i++) {
        $id = $startId + $i - 1;
        $rows[] = [
            'id' => $id,
            'customer_id' => "{$prefix}-{$side}-{$i}",
            'placement_parent_id' => $parentId,
            'placement_side' => $side,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $parentId = $id;
    }

    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('members')->insert($chunk);
    }

    return $parentId;
}

/**
 * Builds $leftTotal + $rightTotal binary-position descendants under
 * $ancestor's two (previously-empty) legs. Returns the two legs' leaf
 * member ids so a caller can place a further "trigger" registration
 * underneath one of them, or grow the team further via extendChain().
 *
 * @return array{left: int, right: int}
 */
function buildTeamOfSize(Member $ancestor, int $leftTotal, int $rightTotal, string $prefix): array
{
    $leftRoot = placeChild($ancestor, 'left', "{$prefix}-L-ROOT");
    $rightRoot = placeChild($ancestor, 'right', "{$prefix}-R-ROOT");

    return [
        'left' => extendChain($leftRoot->id, 'left', $leftTotal - 1, $prefix),
        'right' => extendChain($rightRoot->id, 'right', $rightTotal - 1, $prefix),
    ];
}

function triggerRegistrationEvaluation(Member $member): void
{
    $payment = Payment::create([
        'member_id' => $member->id,
        'type' => 'registration',
        'amount' => 1,
        'mode' => 'cash',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    app(EvaluateBoosterQualification::class)($payment);
}

beforeEach(function () {
    $this->seed();
});

test('reaching Level 1 (10 directs, 500 team split 250L/250R) creates one qualification and 6 monthly schedules of ₹5,000', function () {
    $member = boosterMember('BST-1');
    for ($i = 1; $i <= 12; $i++) {
        boosterMember("BST-1-D{$i}", $member); // 12 directs — Level 1 needs 10.
    }

    $leaves = buildTeamOfSize($member, 260, 260, 'BST1'); // 520 total, 260L/260R — Level 1 needs 250/250.
    triggerRegistrationEvaluation(placeChildById($leaves['left'], 'left', 'BST-1-TRIGGER'));

    $qualifications = BoosterQualification::where('member_id', $member->id)->get();
    expect($qualifications)->toHaveCount(1);
    expect($qualifications[0]->level_no)->toBe(1);

    $schedules = $qualifications[0]->payoutSchedules;
    expect($schedules)->toHaveCount(6);
    expect($schedules->pluck('month_no')->sort()->values()->all())->toBe([1, 2, 3, 4, 5, 6]);
    expect($schedules->every(fn ($s) => (float) $s->amount === 5000.0))->toBeTrue();
    expect($schedules->every(fn ($s) => $s->status === 'pending'))->toBeTrue();
});

test('the Left/Right split is enforced independently — 490 Left / 10 Right (500 total) does not qualify', function () {
    $member = boosterMember('BST-SPLIT');
    for ($i = 1; $i <= 12; $i++) {
        boosterMember("BST-SPLIT-D{$i}", $member); // 12 directs — meets Level 1's directs gate.
    }

    $leaves = buildTeamOfSize($member, 490, 10, 'BSTSPLIT'); // 500 total, but not 250/250.
    triggerRegistrationEvaluation(placeChildById($leaves['left'], 'left', 'BST-SPLIT-TRIGGER'));

    expect(BoosterQualification::where('member_id', $member->id)->count())->toBe(0);
});

test('an unassigned dummy ancestor never becomes a booster beneficiary even if it would otherwise qualify', function () {
    // DOMAIN_LOGIC.md §14.2 point 5 / §21 T-013 pre-coding pass.
    $dummy = Member::create([
        'customer_id' => 'BST-DUMMY-1',
        'status' => 'active',
        'is_company_dummy' => true,
        'dummy_status' => 'unassigned',
    ]);
    for ($i = 1; $i <= 12; $i++) {
        boosterMember("BST-DUMMY-1-D{$i}", $dummy); // Would meet the 10-directs gate.
    }
    $leaves = buildTeamOfSize($dummy, 260, 260, 'BSTDUMMY1'); // Would meet the 250L/250R gate.
    triggerRegistrationEvaluation(placeChildById($leaves['left'], 'left', 'BST-DUMMY-1-TRIGGER'));

    expect(BoosterQualification::where('member_id', $dummy->id)->count())->toBe(0);
});

test('a member can hold concurrent Level 1 and Level 2 schedules, and re-evaluation never duplicates an already-qualified level', function () {
    $member = boosterMember('BST-CONCUR');
    for ($i = 1; $i <= 12; $i++) {
        boosterMember("BST-CONCUR-D{$i}", $member);
    }
    $leaves = buildTeamOfSize($member, 260, 260, 'BSTC1');
    $trigger1 = placeChildById($leaves['left'], 'left', 'BST-CONCUR-T1');
    $leaves['left'] = $trigger1->id;
    triggerRegistrationEvaluation($trigger1);

    expect(BoosterQualification::where('member_id', $member->id)->count())->toBe(1);

    // Re-evaluating again while still only Level-1-eligible must not create a duplicate Level 1 row.
    $trigger1b = placeChildById($leaves['left'], 'left', 'BST-CONCUR-T1B');
    $leaves['left'] = $trigger1b->id;
    triggerRegistrationEvaluation($trigger1b);
    expect(BoosterQualification::where('member_id', $member->id)->where('level_no', 1)->count())->toBe(1);

    // Grow past Level 2's threshold (20 directs, 1,500 team split 750L/750R) by extending the same chains.
    for ($i = 13; $i <= 20; $i++) {
        boosterMember("BST-CONCUR-D{$i}", $member);
    }
    $leaves['left'] = extendChain($leaves['left'], 'left', 490, 'BSTC2'); // 260 + 490 = 750.
    $leaves['right'] = extendChain($leaves['right'], 'right', 490, 'BSTC2');
    triggerRegistrationEvaluation(placeChildById($leaves['left'], 'left', 'BST-CONCUR-T2'));

    $qualifications = BoosterQualification::where('member_id', $member->id)->get()->keyBy('level_no');
    expect($qualifications)->toHaveCount(2);
    expect($qualifications[1])->not->toBeNull(); // Untouched, not recreated.
    expect($qualifications[2]->payoutSchedules)->toHaveCount(6);
    expect($qualifications[2]->payoutSchedules->every(fn ($s) => (float) $s->amount === 20000.0))->toBeTrue();
});

test('ProcessBoosterPayouts credits due schedules, marks them paid, is idempotent, and never merges concurrent levels', function () {
    $member = boosterMember('BST-PAY');
    for ($i = 1; $i <= 20; $i++) {
        boosterMember("BST-PAY-D{$i}", $member);
    }
    $leaves = buildTeamOfSize($member, 750, 750, 'BSTPAY');
    triggerRegistrationEvaluation(placeChildById($leaves['left'], 'left', 'BST-PAY-T1'));

    expect(BoosterQualification::where('member_id', $member->id)->count())->toBe(2);

    app(ProcessBoosterPayouts::class)->handle(app(WalletLedgerService::class));

    $fresh = $member->fresh();
    expect((float) $fresh->wallet_balance)->toBe(25000.0); // Month 1 of both levels: 5,000 + 20,000.

    $entries = WalletLedgerEntry::where('member_id', $member->id)->where('category', 'booster')->get();
    expect($entries)->toHaveCount(2);
    expect($entries->pluck('amount')->map(fn ($v) => (float) $v)->sort()->values()->all())->toBe([5000.0, 20000.0]);

    $paidMonth1Count = BoosterPayoutSchedule::where('month_no', 1)->where('status', 'paid')->count();
    expect($paidMonth1Count)->toBe(2);
    $pendingCount = BoosterPayoutSchedule::where('status', 'pending')->count();
    expect($pendingCount)->toBe(10); // 5 remaining months × 2 levels.

    // Idempotent rerun — no double credit.
    app(ProcessBoosterPayouts::class)->handle(app(WalletLedgerService::class));
    expect((float) $member->fresh()->wallet_balance)->toBe(25000.0);
    expect(WalletLedgerEntry::where('member_id', $member->id)->where('category', 'booster')->count())->toBe(2);
});

test('a later drop in direct count never affects an already-scheduled payout — qualification is a one-time gate', function () {
    $member = boosterMember('BST-DROP');
    for ($i = 1; $i <= 12; $i++) {
        boosterMember("BST-DROP-D{$i}", $member);
    }
    $leaves = buildTeamOfSize($member, 260, 260, 'BSTDROP');
    triggerRegistrationEvaluation(placeChildById($leaves['left'], 'left', 'BST-DROP-T1'));

    // Direct count now drops well below the Level 1 threshold.
    Member::where('sponsor_id', $member->id)->limit(9)->update(['status' => 'cancelled']);
    expect($member->directs()->where('status', 'active')->count())->toBeLessThan(10);

    app(ProcessBoosterPayouts::class)->handle(app(WalletLedgerService::class));

    expect((float) $member->fresh()->wallet_balance)->toBe(5000.0); // Month 1 still pays — no re-check.
});

<?php

use App\Jobs\RunMonthlyDrawExecution;
use App\Models\DrawExecution;
use App\Models\DrawGroup;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\PairEntry;
use App\Models\PairRewardTransaction;
use App\Models\Payment;
use App\Models\RuleValue;
use App\Models\User;

/**
 * `jobs:month-end` / `jobs:draw` — run the scheduler's period jobs on demand for testing. They dispatch the same Job
 * classes the scheduler uses, so only the wiring and output are checked here; the calculations themselves are covered
 * by PairRewardTest / DrawTest.
 */
function mjcMember(string $customerId, ?Member $sponsor = null): Member
{
    $member = Member::create([
        'user_id' => User::factory()->create(['role' => 'member'])->id,
        'customer_id' => $customerId,
        'sponsor_id' => $sponsor?->id,
        'membership_plan_id' => MembershipPlan::where('code', 'E')->value('id'),
        'status' => 'active',
        'activated_at' => now(),
    ]);

    // Registration paid — makes the member a qualified direct for Pair/Reward (T-178).
    Payment::create([
        'member_id' => $member->id,
        'type' => 'registration',
        'amount' => 20000,
        'mode' => 'cash',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    return $member;
}

function mjcPairEntries(Member $beneficiary, string $side, int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        $payment = Payment::create([
            'member_id' => $beneficiary->id,
            'type' => 'registration',
            'amount' => 1,
            'mode' => 'cash',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        PairEntry::create([
            'member_id' => $beneficiary->id,
            'side' => $side,
            'metal' => 'silver',
            'source_payment_id' => $payment->id,
            'status' => 'unused',
        ]);
    }
}

beforeEach(function () {
    $this->seed();
});

test('jobs:month-end pays a reached Pair/Reward milestone and forms draw groups, and is safe to re-run', function () {
    $beneficiary = mjcMember('MJC-PAIR');
    mjcMember('MJC-D1', $beneficiary);
    mjcMember('MJC-D2', $beneficiary);
    mjcPairEntries($beneficiary, 'left', 5);
    mjcPairEntries($beneficiary, 'right', 5);

    RuleValue::where('key', 'draw_group_size')->update(['value' => 3]);

    $this->artisan('jobs:month-end')
        ->expectsOutputToContain('1 milestone(s) paid')
        ->expectsOutputToContain('MJC-PAIR  milestone 1')
        ->expectsOutputToContain('1 new group(s)')
        ->assertExitCode(0);

    expect(PairRewardTransaction::where('member_id', $beneficiary->id)->count())->toBe(1);
    expect(DrawGroup::count())->toBe(1);

    $this->artisan('jobs:month-end')
        ->expectsOutputToContain('0 milestone(s) paid')
        ->expectsOutputToContain('0 new group(s)')
        ->assertExitCode(0);

    expect(PairRewardTransaction::count())->toBe(1);
    expect(DrawGroup::count())->toBe(1);
});

test('jobs:draw draws every active group with the default prize, one cycle month per run (30-09-2026)', function () {
    RuleValue::where('key', 'draw_group_size')->update(['value' => 3]);
    mjcMember('MJC-G1');
    mjcMember('MJC-G2');
    mjcMember('MJC-G3');
    $this->artisan('jobs:month-end')->assertExitCode(0);
    $group = DrawGroup::firstOrFail();

    // No prize set for the group — the draw still runs, with the Silver default.
    $this->artisan('jobs:draw')
        ->expectsOutputToContain('1 group(s) drawn')
        ->expectsOutputToContain("Group #{$group->group_no}  month 1  winner MJC-G")
        ->assertExitCode(0);
    expect(DrawExecution::sole()->prizeConfig()?->prize_name)->toBe('Silver Jewellery');

    // The manual command advances one cycle month per run, even within the same calendar month.
    $this->artisan('jobs:draw')
        ->expectsOutputToContain("Group #{$group->group_no}  month 2  winner MJC-G")
        ->assertExitCode(0);
    expect(DrawExecution::count())->toBe(2);
});

test('the scheduled draw job never draws a group twice in one calendar month', function () {
    RuleValue::where('key', 'draw_group_size')->update(['value' => 3]);
    mjcMember('MJC-S1');
    mjcMember('MJC-S2');
    mjcMember('MJC-S3');
    $this->artisan('jobs:month-end')->assertExitCode(0);

    RunMonthlyDrawExecution::dispatchSync();
    RunMonthlyDrawExecution::dispatchSync();

    expect(DrawExecution::count())->toBe(1);
});

<?php

use App\Actions\Draw\ExecuteMonthlyDraw;
use App\Actions\Draw\GenerateDrawGroups;
use App\Models\DrawExecution;
use App\Models\DrawGroup;
use App\Models\DrawGroupMonthConfig;
use App\Models\EmiInstallment;
use App\Models\EmiSchedule;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\RuleValue;
use App\Models\User;

/**
 * DOMAIN_LOGIC.md §8 (Monthly Draw), Docs/TEST.md scenario 6 — grouping
 * (full-batch-only, eligibility filtering), execution (winner selection,
 * idempotency, cycle completion), and the upline-benefit threshold.
 */
function drawMember(string $customerId, ?Member $sponsor = null, string $planCode = 'E', string $status = 'active'): Member
{
    $user = User::factory()->create(['role' => 'member']);
    $plan = MembershipPlan::where('code', $planCode)->first();

    return Member::create([
        'user_id' => $user->id,
        'customer_id' => $customerId,
        'sponsor_id' => $sponsor?->id,
        'membership_plan_id' => $plan?->id,
        'status' => $status,
        'activated_at' => $status === 'active' ? now() : null,
    ]);
}

function drawEmiMember(string $customerId, string $planCode, int $completedInstallments): Member
{
    $member = drawMember($customerId, null, $planCode);
    $plan = MembershipPlan::where('code', $planCode)->first();

    $schedule = EmiSchedule::create([
        'member_id' => $member->id,
        'membership_plan_id' => $plan->id,
        'total_installments' => $plan->installment_count,
        'rate_booking_method' => 'future_rate',
        'installment_amount' => $plan->amount,
    ]);

    for ($i = 1; $i <= $plan->installment_count; $i++) {
        EmiInstallment::create([
            'emi_schedule_id' => $schedule->id,
            'installment_no' => $i,
            'due_date' => now()->addMonths($i),
            'amount' => $plan->amount,
            'status' => $i <= $completedInstallments ? 'paid' : 'upcoming',
        ]);
    }

    return $member->fresh();
}

function formGroupOf(int $groupSize, int $memberCount, string $prefix): DrawGroup
{
    RuleValue::where('key', 'draw_group_size')->update(['value' => $groupSize]);

    for ($i = 1; $i <= $memberCount; $i++) {
        drawMember(sprintf('%s%03d', $prefix, $i));
    }

    return app(GenerateDrawGroups::class)()[0];
}

function configureMonth(DrawGroup $group, int $monthNo, string $metal = 'silver'): DrawGroupMonthConfig
{
    return DrawGroupMonthConfig::create([
        'draw_group_id' => $group->id,
        'cycle_month_no' => $monthNo,
        'prize_name' => "{$metal} prize month {$monthNo}",
        'prize_value' => 5000,
        'metal_type' => $metal,
    ]);
}

beforeEach(function () {
    $this->seed();
});

test('grouping forms exactly one full group from a 250-member eligible backlog, leaving 50 ungrouped', function () {
    for ($i = 1; $i <= 250; $i++) {
        drawMember(sprintf('DGM%03d', $i));
    }

    $groups = app(GenerateDrawGroups::class)();

    expect($groups)->toHaveCount(1);
    expect($groups[0]->size)->toBe(200);
    expect($groups[0]->members()->count())->toBe(200);
    expect(DrawGroup::count())->toBe(1);

    // Idempotent rerun — no second (partial) group from the remaining 50.
    $again = app(GenerateDrawGroups::class)();
    expect($again)->toHaveCount(0);
    expect(DrawGroup::count())->toBe(1);

    // 150 more arrive — 50 leftover + 150 new = exactly one more full group.
    for ($i = 251; $i <= 400; $i++) {
        drawMember(sprintf('DGM%03d', $i));
    }

    $third = app(GenerateDrawGroups::class)();
    expect($third)->toHaveCount(1);
    expect(DrawGroup::count())->toBe(2);
    expect($third[0]->members()->count())->toBe(200);
});

test('a below-threshold EMI member and an unassigned dummy are excluded from grouping', function () {
    $belowThreshold = drawEmiMember('DGM-EMI-1', 'A', 3); // Plan A needs 6.
    $dummy = Member::create([
        'user_id' => User::factory()->create(['role' => 'member'])->id,
        'customer_id' => 'DGM-DUMMY-1',
        'status' => 'active',
        'activated_at' => now(),
        'is_company_dummy' => true,
        'dummy_status' => 'unassigned',
    ]);

    RuleValue::where('key', 'draw_group_size')->update(['value' => 200]);
    for ($i = 1; $i <= 200; $i++) {
        drawMember(sprintf('DGM-FILL%03d', $i));
    }

    $groups = app(GenerateDrawGroups::class)();

    expect($groups)->toHaveCount(1);
    $memberIds = $groups[0]->members()->pluck('member_id');
    expect($memberIds)->not->toContain($belowThreshold->id);
    expect($memberIds)->not->toContain($dummy->id);
});

test('execution selects a winner, removes them from the pool, and is idempotent per month', function () {
    $group = formGroupOf(10, 10, 'DEX');
    configureMonth($group, 1);

    $executions = app(ExecuteMonthlyDraw::class)();
    expect($executions)->toHaveCount(1);
    expect($executions[0]->cycle_month_no)->toBe(1);

    $winnerId = $executions[0]->winner_member_id;
    expect($group->members()->where('member_id', $winnerId)->first()->is_winner_removed)->toBeTrue();
    expect($group->members()->where('is_winner_removed', false)->count())->toBe(9);

    // Retried same-month execution — idempotent no-op.
    $retry = app(ExecuteMonthlyDraw::class)();
    expect($retry)->toHaveCount(0);
    expect(DrawExecution::where('draw_group_id', $group->id)->count())->toBe(1);

    // Month 2 configured — never re-selects the month-1 winner.
    configureMonth($group, 2);
    $month2 = app(ExecuteMonthlyDraw::class)();
    expect($month2)->toHaveCount(1);
    expect($month2[0]->cycle_month_no)->toBe(2);
    expect($month2[0]->winner_member_id)->not->toBe($winnerId);
});

test('a group is skipped when its current month has no configured prize, without blocking other groups', function () {
    $unconfigured = formGroupOf(10, 10, 'DEXU');
    $configured = formGroupOf(10, 10, 'DEXC');
    configureMonth($configured, 1);

    $executions = app(ExecuteMonthlyDraw::class)();

    expect($executions)->toHaveCount(1);
    expect($executions[0]->draw_group_id)->toBe($configured->id);
    expect(DrawExecution::where('draw_group_id', $unconfigured->id)->count())->toBe(0);
});

test('a group completes after its 20th cycle month and never executes a 21st', function () {
    $group = formGroupOf(20, 20, 'DEX20');

    for ($month = 1; $month <= 20; $month++) {
        configureMonth($group, $month);
        app(ExecuteMonthlyDraw::class)();
    }

    expect($group->fresh()->status)->toBe('completed');
    expect(DrawExecution::where('draw_group_id', $group->id)->count())->toBe(20);

    configureMonth($group, 21);
    $extra = app(ExecuteMonthlyDraw::class)();
    expect($extra)->toHaveCount(0);
});

test('upline draw benefit fires at exactly 10 Direct Members', function () {
    $sponsor = drawMember('DEX-SPONSOR-Q');
    // 9 other directs + the winner (also one of the sponsor's directs) = 10 total.
    for ($i = 1; $i <= 9; $i++) {
        drawMember("DEX-SPONSOR-Q-D{$i}", $sponsor);
    }

    $winner = drawMember('DEX-WINNER-Q', $sponsor);

    // Group size 1 makes RNG selection deterministic (only one candidate).
    RuleValue::where('key', 'draw_group_size')->update(['value' => 1]);
    $groups = app(GenerateDrawGroups::class)();
    $group = collect($groups)->first(fn ($g) => $g->members()->where('member_id', $winner->id)->exists());
    configureMonth($group, 1);

    $execution = app(ExecuteMonthlyDraw::class)()[0];

    expect($execution->winner_member_id)->toBe($winner->id);
    expect($execution->upline_benefit_member_id)->toBe($sponsor->id);
});

test('no upline draw benefit at only 9 Direct Members, and the winner still gets their own prize', function () {
    $sponsor = drawMember('DEX-SPONSOR-N');
    // 8 other directs + the winner (also one of the sponsor's directs) = 9 total.
    for ($i = 1; $i <= 8; $i++) {
        drawMember("DEX-SPONSOR-N-D{$i}", $sponsor);
    }

    $winner = drawMember('DEX-WINNER-N', $sponsor);

    RuleValue::where('key', 'draw_group_size')->update(['value' => 1]);
    $groups = app(GenerateDrawGroups::class)();
    $group = collect($groups)->first(fn ($g) => $g->members()->where('member_id', $winner->id)->exists());
    configureMonth($group, 1);

    $execution = app(ExecuteMonthlyDraw::class)()[0];

    expect($execution->winner_member_id)->toBe($winner->id);
    expect($execution->upline_benefit_member_id)->toBeNull();
});

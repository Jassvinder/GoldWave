<?php

use App\Actions\Draw\ExecuteMonthlyDraw;
use App\Actions\Draw\GenerateDrawGroups;
use App\Jobs\RunMonthlyDrawExecution;
use App\Models\DrawExecution;
use App\Models\DrawGroup;
use App\Models\DrawGroupMonthConfig;
use App\Models\EmiInstallment;
use App\Models\EmiSchedule;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\RuleValue;
use App\Models\RuleVersion;
use App\Models\User;
use App\Services\RuleVersionService;

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

    // Retried in the same calendar month — idempotent no-op, even though month 2 has a (default) prize.
    $retry = app(ExecuteMonthlyDraw::class)();
    expect($retry)->toHaveCount(0);
    expect(DrawExecution::where('draw_group_id', $group->id)->count())->toBe(1);

    // Next calendar month — month 2 is drawn and never re-selects the month-1 winner.
    $this->travel(1)->months();
    $month2 = app(ExecuteMonthlyDraw::class)();
    expect($month2)->toHaveCount(1);
    expect($month2[0]->cycle_month_no)->toBe(2);
    expect($month2[0]->winner_member_id)->not->toBe($winnerId);
});

test('a month with no prize of its own still draws, with the Silver default frozen on it (30-09-2026)', function () {
    $unconfigured = formGroupOf(10, 10, 'DEXU');
    $configured = formGroupOf(10, 10, 'DEXC');
    configureMonth($configured, 1);

    $executions = app(ExecuteMonthlyDraw::class)();

    expect($executions)->toHaveCount(2);

    // The group's own prize wins over the default.
    expect(DrawGroupMonthConfig::where('draw_group_id', $configured->id)->where('cycle_month_no', 1)->sole()->prize_name)
        ->toBe('silver prize month 1');

    // No prize of its own → the Silver default (months 1–15) is snapshotted.
    $snapshot = DrawGroupMonthConfig::where('draw_group_id', $unconfigured->id)->where('cycle_month_no', 1)->sole();
    expect($snapshot->prize_name)->toBe('Silver Jewellery')
        ->and((float) $snapshot->prize_value)->toBe(20000.0)
        ->and($snapshot->metal_type)->toBe('silver');

    // A later change to the default never rewrites a past draw's prize.
    RuleValue::updateOrCreate(
        ['rule_version_id' => RuleVersion::where('is_active', true)->value('id'), 'key' => 'draw_prize_silver_value'],
        ['value' => 30000],
    );
    expect((float) $snapshot->fresh()->prize_value)->toBe(20000.0);
});

test('months 16–20 fall back to the Gold default', function () {
    $group = formGroupOf(20, 20, 'DEXG');

    for ($month = 1; $month <= 16; $month++) {
        app(ExecuteMonthlyDraw::class)(oncePerCalendarMonth: false);
    }

    $month15 = DrawGroupMonthConfig::where('draw_group_id', $group->id)->where('cycle_month_no', 15)->sole();
    $month16 = DrawGroupMonthConfig::where('draw_group_id', $group->id)->where('cycle_month_no', 16)->sole();

    expect($month15->metal_type)->toBe('silver');
    expect($month16->prize_name)->toBe('Gold Jewellery')
        ->and((float) $month16->prize_value)->toBe(25000.0)
        ->and($month16->metal_type)->toBe('gold');
});

test('a group completes after its 20th cycle month and never executes a 21st', function () {
    $group = formGroupOf(20, 20, 'DEX20');

    for ($month = 1; $month <= 20; $month++) {
        app(ExecuteMonthlyDraw::class)(oncePerCalendarMonth: false);
    }

    expect($group->fresh()->status)->toBe('completed');
    expect(DrawExecution::where('draw_group_id', $group->id)->count())->toBe(20);

    $extra = app(ExecuteMonthlyDraw::class)(oncePerCalendarMonth: false);
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

    // Every size-1 group draws now (a default prize always applies), so pick this group's draw.
    $execution = collect(app(ExecuteMonthlyDraw::class)())->firstWhere('draw_group_id', $group->id);

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

    // Every size-1 group draws now (a default prize always applies), so pick this group's draw.
    $execution = collect(app(ExecuteMonthlyDraw::class)())->firstWhere('draw_group_id', $group->id);

    expect($execution->winner_member_id)->toBe($winner->id);
    expect($execution->upline_benefit_member_id)->toBeNull();
});

test('a month set to 3 winners picks 3 different members, numbered 1–3, and the next run draws month 2 (30-09-2026)', function () {
    $group = formGroupOf(10, 10, 'DEXM');
    configureMonth($group, 1)->update(['winners_count' => 3]);

    $executions = app(ExecuteMonthlyDraw::class)();

    expect($executions)->toHaveCount(3)
        ->and(collect($executions)->pluck('winner_no')->all())->toBe([1, 2, 3])
        ->and(collect($executions)->pluck('cycle_month_no')->unique()->all())->toBe([1])
        ->and(collect($executions)->pluck('winner_member_id')->unique())->toHaveCount(3);
    expect($group->members()->where('is_winner_removed', false)->count())->toBe(7);

    $this->travel(1)->months();
    $next = app(ExecuteMonthlyDraw::class)();

    expect($next)->toHaveCount(1)
        ->and($next[0]->cycle_month_no)->toBe(2);
});

test('a sponsor with 10+ directs gets the upline benefit every time one of them wins — no limit (T-192)', function () {
    $sponsor = drawMember('DEX-SPONSOR-U');
    // Group the sponsor (and anyone seeded) first, so the next group holds only the sponsor's 12 directs.
    RuleValue::where('key', 'draw_group_size')->update(['value' => 1]);
    app(GenerateDrawGroups::class)();

    for ($i = 1; $i <= 12; $i++) {
        drawMember(sprintf('DEX-SPONSOR-U-D%02d', $i), $sponsor);
    }

    RuleValue::where('key', 'draw_group_size')->update(['value' => 12]);
    $group = app(GenerateDrawGroups::class)()[0];
    DrawGroup::whereKeyNot($group->id)->update(['status' => 'completed']);
    expect($group->members()->whereHas('member', fn ($q) => $q->where('sponsor_id', $sponsor->id))->count())->toBe(12);
    configureMonth($group, 1)->update(['winners_count' => 3]);

    // Month 1 picks 3 winners, months 2–3 one each: 5 wins in total.
    app(ExecuteMonthlyDraw::class)(oncePerCalendarMonth: false);
    app(ExecuteMonthlyDraw::class)(oncePerCalendarMonth: false);
    app(ExecuteMonthlyDraw::class)(oncePerCalendarMonth: false);

    $executions = DrawExecution::where('draw_group_id', $group->id)->get();
    expect($executions)->toHaveCount(5)
        ->and($executions->where('upline_benefit_member_id', $sponsor->id))->toHaveCount(5);
});

test('the upline benefit shows on the sponsor\'s Draw page and Dashboard and on the winner\'s row; the group header explains itself (T-199/T-200)', function () {
    $sponsor = drawMember('DEX-SPONSOR-V');
    RuleValue::where('key', 'draw_group_size')->update(['value' => 1]);
    app(GenerateDrawGroups::class)(); // groups the sponsor (and anyone seeded) on their own

    for ($i = 1; $i <= 10; $i++) {
        drawMember(sprintf('DEX-SPONSOR-V-D%02d', $i), $sponsor);
    }
    RuleValue::where('key', 'draw_group_size')->update(['value' => 10]);
    $group = app(GenerateDrawGroups::class)()[0];
    DrawGroup::whereKeyNot($group->id)->update(['status' => 'completed']);

    $execution = app(ExecuteMonthlyDraw::class)()[0];
    $winner = $execution->winner;
    expect($execution->upline_benefit_member_id)->toBe($sponsor->id);

    // Sponsor: listed under "Upline benefits you received", even though they are not in this group.
    $this->actingAs($sponsor->user)->get('/member/draw')
        ->assertInertia(fn ($page) => $page
            ->where('upline_benefits.0.winner_customer_id', $winner->customer_id)
            ->where('upline_benefits.0.group_no', $group->group_no)
            ->where('upline_benefits.0.prize_name', 'Silver Jewellery'));

    $this->actingAs($sponsor->user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('draw.upline_benefits', 1)
            ->where('draw.latest_upline.winner_customer_id', $winner->customer_id));

    // Winner: their row names the Sponsor; the header states the group, its range and progress.
    $this->actingAs($winner->user)->get('/member/draw')
        ->assertInertia(fn ($page) => $page
            ->where('groups.0.executions.0.upline_benefit_customer_id', 'DEX-SPONSOR-V')
            ->where('groups.0.group_no', $group->group_no)
            ->where('groups.0.first_customer_id', 'DEX-SPONSOR-V-D01')
            ->where('groups.0.last_customer_id', 'DEX-SPONSOR-V-D10')
            ->where('groups.0.size', 10)
            ->where('groups.0.winners_count', 1)
            ->where('groups.0.draws_held', 1)
            ->where('groups.0.next_draw.cycle_month_no', 2));

    $this->actingAs($winner->user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('draw.won', 1)
            // 01-10-2026 — the win names its prize and value on the Dashboard.
            ->where('draw.wins.0.prize_name', 'Silver Jewellery')
            ->where('draw.wins.0.prize_value', '20000.00')
            ->where('draw.wins.0.cycle_month_no', 1));
});

function setAutoDraw(bool $enabled): void
{
    RuleValue::updateOrCreate(
        ['rule_version_id' => RuleVersion::where('is_active', true)->value('id'), 'key' => 'draw_auto_enabled'],
        ['value' => $enabled],
    );
}

test('with the automatic draw off, the 15th job and jobs:draw draw nothing, but groups still form (T-221)', function () {
    setAutoDraw(false);
    $group = formGroupOf(5, 5, 'DMO');

    RunMonthlyDrawExecution::dispatchSync();
    $this->artisan('jobs:draw')->assertFailed();

    expect($group->status)->toBe('active')
        ->and(DrawExecution::count())->toBe(0);
});

test('a manual draw result is recorded like the automatic one and shows on the member page (T-221)', function () {
    setAutoDraw(false);
    $sponsor = drawMember('DMS-SPONSOR');
    RuleValue::where('key', 'draw_group_size')->update(['value' => 1]);
    app(GenerateDrawGroups::class)(); // the sponsor in a group of their own
    for ($i = 1; $i <= 10; $i++) {
        drawMember(sprintf('DMS%02d', $i), $sponsor);
    }
    RuleValue::where('key', 'draw_group_size')->update(['value' => 10]);
    $group = app(GenerateDrawGroups::class)()[0];
    $admin = User::factory()->create(['role' => 'admin', 'name' => 'Desk Admin']);

    // Typed in lower case with spaces — still found.
    $this->actingAs($admin)
        ->post("/super-admin/draw-management/groups/{$group->id}/manual-result", ['customer_ids' => [' dms07 ']])
        ->assertRedirect('/super-admin/draw-management')
        ->assertSessionHasNoErrors();

    $execution = DrawExecution::where('draw_group_id', $group->id)->sole();
    $winner = Member::where('customer_id', 'DMS07')->first();
    expect($execution->winner_member_id)->toBe($winner->id)
        ->and($execution->cycle_month_no)->toBe(1)
        ->and($execution->status)->toBe('executed')
        ->and($execution->upline_benefit_member_id)->toBe($sponsor->id)
        ->and(json_decode($execution->rng_proof, true))->toMatchArray(['algorithm' => 'manual', 'entered_by' => 'Desk Admin'])
        ->and($group->members()->where('member_id', $winner->id)->value('is_winner_removed'))->toBeTrue()
        ->and(DrawGroupMonthConfig::where('draw_group_id', $group->id)->where('cycle_month_no', 1)->value('prize_name'))->toBe('Silver Jewellery');

    $this->actingAs($winner->user)->get('/member/draw')
        ->assertInertia(fn ($page) => $page
            ->where('groups.0.executions.0.winner_customer_id', 'DMS07')
            ->where('groups.0.executions.0.is_own_win', true)
            ->where('groups.0.executions.0.prize_name', 'Silver Jewellery'));

    $this->actingAs($admin)->get('/super-admin/draw-management')
        ->assertInertia(fn ($page) => $page
            ->where('auto_draw_enabled', false)
            ->where('groups.0.drawn_this_month', true)
            ->where('groups.0.executions.0.entered_by', 'Desk Admin')
            ->where('groups.0.remaining_members', fn ($members) => count($members) === 9));

    // One draw per calendar month: a second entry this month is refused.
    $this->actingAs($admin)
        ->post("/super-admin/draw-management/groups/{$group->id}/manual-result", ['customer_ids' => ['DMS01']])
        ->assertSessionHasErrors('customer_ids');
    expect(DrawExecution::count())->toBe(1);
});

test('a manual draw result is refused for a wrong, repeated or already-won ID, a wrong count, or while the automatic draw is on (T-221)', function () {
    $group = formGroupOf(5, 5, 'DMR');
    drawMember('DMR-OUTSIDER');
    $admin = User::factory()->create(['role' => 'super_admin']);
    $url = "/super-admin/draw-management/groups/{$group->id}/manual-result";

    // Automatic draw on (the default).
    $this->actingAs($admin)->post($url, ['customer_ids' => ['DMR001']])->assertSessionHasErrors('customer_ids');

    setAutoDraw(false);
    $this->actingAs($admin)->post($url, ['customer_ids' => ['DMR-OUTSIDER']])->assertSessionHasErrors('customer_ids.0');
    $this->actingAs($admin)->post($url, ['customer_ids' => ['DMR001', 'DMR002']])->assertSessionHasErrors('customer_ids');

    $group->monthConfigs()->create(['cycle_month_no' => 1, 'prize_name' => 'Pair', 'prize_value' => 1000, 'metal_type' => 'silver', 'winners_count' => 2]);
    $this->actingAs($admin)->post($url, ['customer_ids' => ['DMR001', 'dmr001']])->assertSessionHasErrors('customer_ids.1');
    expect(DrawExecution::count())->toBe(0);

    $this->actingAs($admin)->post($url, ['customer_ids' => ['DMR001', 'DMR003']])->assertSessionHasNoErrors();
    expect(DrawExecution::where('draw_group_id', $group->id)->orderBy('winner_no')->pluck('winner_member_id')->all())
        ->toBe(Member::whereIn('customer_id', ['DMR001', 'DMR003'])->orderBy('customer_id')->pluck('id')->all());

    // Next month, a member who already won can't be drawn again.
    $this->travel(1)->months();
    $this->actingAs($admin)->post($url, ['customer_ids' => ['DMR003']])->assertSessionHasErrors('customer_ids.0');
    $this->actingAs($admin)->post($url, ['customer_ids' => ['DMR004']])->assertSessionHasNoErrors();
    expect(DrawExecution::where('cycle_month_no', 2)->sole()->winner->customer_id)->toBe('DMR004');
});

test('Draw Settings saves the automatic draw switch (T-221)', function () {
    $this->actingAs(User::factory()->create(['role' => 'super_admin']))
        ->post('/super-admin/draw-settings', [
            'draw_auto_enabled' => false,
            'draw_group_size' => 200,
            'draw_prize_silver_name' => 'Silver Jewellery',
            'draw_prize_silver_value' => 20000,
            'draw_prize_gold_name' => 'Gold Jewellery',
            'draw_prize_gold_value' => 25000,
            'draw_winners_per_month' => 1,
        ])
        ->assertRedirect('/super-admin/draw-settings');

    expect(ExecuteMonthlyDraw::autoDrawEnabled(app(RuleVersionService::class)))->toBeFalse();
});

<?php

use App\Models\DashboardNotice;
use App\Models\Member;
use App\Models\MetalRate;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * T-245 (DOMAIN_LOGIC.md §2.2b, INSTRUCTIONS.md M01/S19) — offers/events/notices managed by Super Admin or the company
 * Admin, shown with today's gold/silver rate at the top of the member dashboard.
 */
function noticeMember(): Member
{
    return Member::create([
        'user_id' => User::factory()->create(['role' => 'member'])->id,
        'customer_id' => 'GWL800',
        'status' => 'active',
        'activated_at' => now(),
    ]);
}

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 11, 15, 10));
    $this->seed();
});

afterEach(function () {
    Carbon::setTestNow();
});

test('the company Admin adds, edits and deletes a notice; a Store Admin and a member cannot', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post('/super-admin/dashboard-notices', [
        'kind' => 'offer', 'title' => 'Diwali offer', 'body' => 'No making charges', 'starts_on' => null,
        'ends_on' => '2026-11-20', 'is_active' => true,
    ])->assertSessionHasNoErrors();

    $notice = DashboardNotice::firstOrFail();
    expect($notice->created_by)->toBe($admin->id)->and($notice->ends_on->toDateString())->toBe('2026-11-20');

    $this->actingAs($admin)->get('/super-admin/dashboard-notices')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('super-admin/dashboard-notices')
            ->where('notices.0.state', 'showing')->where('notices.0.updated_by', $admin->name));

    $this->actingAs($admin)->patch("/super-admin/dashboard-notices/{$notice->id}", [
        'kind' => 'event', 'title' => 'Diwali event', 'body' => null, 'starts_on' => null, 'ends_on' => null, 'is_active' => false,
    ])->assertSessionHasNoErrors();
    expect($notice->fresh()->kind)->toBe('event')->and($notice->fresh()->is_active)->toBeFalse()
        ->and($notice->fresh()->updated_by)->toBe($admin->id);

    // The end date can't be before the start date.
    $this->actingAs($admin)->post('/super-admin/dashboard-notices', [
        'kind' => 'notice', 'title' => 'Bad dates', 'starts_on' => '2026-11-20', 'ends_on' => '2026-11-19', 'is_active' => true,
    ])->assertSessionHasErrors('ends_on');

    $this->actingAs(User::factory()->create(['role' => 'store_admin']))->get('/super-admin/dashboard-notices')->assertForbidden();
    $this->actingAs(noticeMember()->user)->post('/super-admin/dashboard-notices', ['kind' => 'offer', 'title' => 'x', 'is_active' => true])->assertForbidden();

    $this->actingAs($admin)->delete("/super-admin/dashboard-notices/{$notice->id}")->assertSessionHasNoErrors();
    expect(DashboardNotice::count())->toBe(0);
});

test('the member dashboard shows today\'s rates and only the notices running today, offers first', function () {
    $superAdmin = User::where('role', 'super_admin')->firstOrFail();
    $member = noticeMember();
    $make = fn (array $attrs) => DashboardNotice::create([...['kind' => 'notice', 'body' => null, 'is_active' => true, 'created_by' => $superAdmin->id], ...$attrs]);

    $make(['title' => 'Office closed Sunday']);
    $make(['kind' => 'offer', 'title' => 'Diwali offer', 'ends_on' => '2026-11-15']);   // last day today: shown
    $make(['kind' => 'event', 'title' => 'Future meet', 'starts_on' => '2026-11-16']);   // not started
    $make(['kind' => 'event', 'title' => 'Old meet', 'ends_on' => '2026-11-14']);        // ended
    $make(['kind' => 'offer', 'title' => 'Hidden', 'is_active' => false]);               // turned off

    MetalRate::create(['metal' => 'gold', 'rate_per_gram' => 7000, 'effective_from' => '2026-11-10', 'created_by' => $superAdmin->id]);
    MetalRate::create(['metal' => 'gold', 'rate_per_gram' => 7100, 'effective_from' => '2026-11-15', 'created_by' => $superAdmin->id]);
    MetalRate::create(['metal' => 'gold', 'rate_per_gram' => 9999, 'effective_from' => '2026-11-16', 'created_by' => $superAdmin->id]); // tomorrow

    $this->actingAs($member->user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->component('member/dashboard')
            ->has('notices', 2)
            ->where('notices.0.title', 'Diwali offer')
            ->where('notices.0.ends_on', '2026-11-15')
            ->where('notices.1.title', 'Office closed Sunday')
            ->where('todays_rates.0.metal', 'gold')
            ->where('todays_rates.0.rate_per_gram', '7100.00')
            ->where('todays_rates.0.effective_from', '2026-11-15'));
});

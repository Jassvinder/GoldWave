<?php

use App\Models\BannerWinner;
use App\Models\Member;
use App\Models\MetalRate;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * T-248/T-249 (DOMAIN_LOGIC.md §8.8, INSTRUCTIONS.md M01/S20) — Banner Winners typed in by Super Admin or the company
 * Admin, and the welcome banner a member sees once after every login.
 */
function bannerMember(string $customerId, string $name): Member
{
    return Member::create([
        'user_id' => User::factory()->create(['role' => 'member', 'name' => $name, 'password' => $customerId])->id,
        'customer_id' => $customerId,
        'status' => 'active',
        'activated_at' => now(),
    ]);
}

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 11, 24, 10));
    $this->seed();
});

afterEach(function () {
    Carbon::setTestNow();
});

test('the company Admin adds, edits and removes a winner by Customer ID; the name comes from the member', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $winner = bannerMember('GWL901', 'Asha Rani');
    bannerMember('GWL902', 'Ravi Kumar');

    // Case and spaces in the Customer ID don't matter.
    $this->actingAs($admin)->post('/super-admin/banner-winners', [
        'group_no' => 2, 'draw_month' => '2026-11', 'customer_id' => ' gwl 901 ', 'prize' => 'Silver Jewellery',
    ])->assertSessionHasNoErrors();

    $row = BannerWinner::firstOrFail();
    expect($row->member_id)->toBe($winner->id)
        ->and($row->draw_month->toDateString())->toBe('2026-11-01')
        ->and($row->created_by)->toBe($admin->id);

    $this->actingAs($admin)->get('/super-admin/banner-winners')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('super-admin/banner-winners')
            ->where('winners.0.name', 'Asha Rani')
            ->where('winners.0.customer_id', 'GWL901')
            ->where('winners.0.month_label', 'November 2026')
            ->where('winners.0.on_banner', true));

    $this->actingAs($admin)->patch("/super-admin/banner-winners/{$row->id}", [
        'group_no' => 3, 'draw_month' => '2026-11', 'customer_id' => 'GWL902', 'prize' => 'Gold Coin',
    ])->assertSessionHasNoErrors();
    expect($row->fresh()->group_no)->toBe(3)
        ->and($row->fresh()->member->customer_id)->toBe('GWL902')
        ->and($row->fresh()->updated_by)->toBe($admin->id);

    $this->actingAs($admin)->delete("/super-admin/banner-winners/{$row->id}")->assertSessionHasNoErrors();
    expect(BannerWinner::count())->toBe(0);
});

test('an unknown Customer ID and the same member twice in one month are refused; others cannot open the page', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
    $member = bannerMember('GWL903', 'Meena Devi');

    $this->actingAs($admin)->post('/super-admin/banner-winners', [
        'group_no' => 1, 'draw_month' => '2026-11', 'customer_id' => 'GWL000404', 'prize' => 'Silver Jewellery',
    ])->assertSessionHasErrors('customer_id');

    $valid = ['group_no' => 1, 'draw_month' => '2026-11', 'customer_id' => 'GWL903', 'prize' => 'Silver Jewellery'];
    $this->actingAs($admin)->post('/super-admin/banner-winners', $valid)->assertSessionHasNoErrors();
    $this->actingAs($admin)->post('/super-admin/banner-winners', [...$valid, 'group_no' => 4])->assertSessionHasErrors('customer_id');

    // Saving the same row again (edit without changing the member) is allowed; another month is a new entry.
    $row = BannerWinner::firstOrFail();
    $this->actingAs($admin)->patch("/super-admin/banner-winners/{$row->id}", [...$valid, 'prize' => 'Silver Coin'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post('/super-admin/banner-winners', [...$valid, 'draw_month' => '2026-12'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post('/super-admin/banner-winners', [...$valid, 'draw_month' => '11-2026'])->assertSessionHasErrors('draw_month');

    $this->actingAs(User::factory()->create(['role' => 'store_admin']))->get('/super-admin/banner-winners')->assertForbidden();
    $this->actingAs($member->user)->post('/super-admin/banner-winners', $valid)->assertForbidden();
});

test('the banner opens on the first dashboard visit after each login with the latest month winners and today\'s rate', function () {
    $member = bannerMember('GWL904', 'Sunita');
    $older = bannerMember('GWL905', 'Old Winner');
    $groupTwo = bannerMember('GWL906', 'Group Two');
    $groupOne = bannerMember('GWL907', 'Group One');
    $creator = User::factory()->create(['role' => 'admin'])->id;

    BannerWinner::create(['group_no' => 1, 'draw_month' => '2026-10-01', 'member_id' => $older->id, 'prize' => 'Old Prize', 'created_by' => $creator]);
    BannerWinner::create(['group_no' => 2, 'draw_month' => '2026-11-01', 'member_id' => $groupTwo->id, 'prize' => 'Silver Coin', 'created_by' => $creator]);
    BannerWinner::create(['group_no' => 1, 'draw_month' => '2026-11-01', 'member_id' => $groupOne->id, 'prize' => 'Silver Jewellery', 'created_by' => $creator]);
    MetalRate::create(['metal' => 'gold', 'rate_per_gram' => 12385, 'making_charge_percent' => 0, 'effective_from' => '2026-11-24', 'created_by' => $creator]);

    $this->post('/member/login/password', ['customer_id' => 'GWL904', 'password' => 'GWL904'])->assertRedirect();
    $this->assertAuthenticatedAs($member->user);

    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('member/dashboard')
            ->where('login_banner.winners.month', 'November 2026')
            ->has('login_banner.winners.list', 2)
            ->where('login_banner.winners.list.0.group_no', 1)
            ->where('login_banner.winners.list.0.name', 'Group One')
            ->where('login_banner.winners.list.0.customer_id', 'GWL907')
            ->where('login_banner.winners.list.1.prize', 'Silver Coin')
            ->where('todays_rates.0.metal', 'gold')
            ->has('login_banner.key'));

    // A refresh or a later visit in the same session doesn't open it again.
    $this->get('/dashboard')->assertInertia(fn ($page) => $page->where('login_banner', null));

    // The next login opens it again.
    auth()->logout();
    $this->post('/member/login/password', ['customer_id' => 'GWL904', 'password' => 'GWL904'])->assertRedirect();
    $this->get('/dashboard')->assertInertia(fn ($page) => $page->has('login_banner.key'));
});

test('with no winners listed the banner still opens, without a winners list', function () {
    bannerMember('GWL908', 'No Winners Yet');

    $this->post('/member/login/password', ['customer_id' => 'GWL908', 'password' => 'GWL908'])->assertRedirect();

    $this->get('/dashboard')->assertInertia(fn ($page) => $page->where('login_banner.winners', null)->has('login_banner.key'));
});

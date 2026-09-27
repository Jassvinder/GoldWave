<?php

use App\Models\Member;
use App\Models\Store;
use App\Models\User;
use App\Services\BinaryTeamSizeCounter;
use App\Services\MemberNetworkSummary;

/**
 * T-129 — Docs/TEST.md scenario 19. Tree under R (sponsor of L1/R1/R2 is R):
 *   R -> left L1, right R1
 *   L1 -> left L2 (draft), right R2 (Store Owner)
 *   L2 -> left L3
 *   R1 -> right R3 (unassigned company dummy)
 */
function nsMember(string $customerId, ?Member $parent = null, ?string $side = null, ?Member $sponsor = null, array $extra = []): Member
{
    $user = User::factory()->create(['role' => 'member']);

    return Member::create([
        'user_id' => $user->id,
        'customer_id' => $customerId,
        'sponsor_id' => $sponsor?->id,
        'placement_parent_id' => $parent?->id,
        'placement_side' => $side,
        'status' => 'active',
        'activated_at' => now(),
        ...$extra,
    ]);
}

/** @return array<string, Member> */
function nsTree(): array
{
    $r = nsMember('NS-R');
    $l1 = nsMember('NS-L1', $r, 'left', $r);
    $r1 = nsMember('NS-R1', $r, 'right', $r);
    $l2 = nsMember('NS-L2', $l1, 'left', null, ['status' => 'draft']);
    $r2 = nsMember('NS-R2', $l1, 'right', $r);
    $l3 = nsMember('NS-L3', $l2, 'left');
    $r3 = nsMember('NS-R3', $r1, 'right', null, ['is_company_dummy' => true, 'dummy_status' => 'unassigned']);

    Store::create(['name' => 'Owner Jewellers', 'owner_user_id' => $r2->user_id]);

    return compact('r', 'l1', 'r1', 'l2', 'r2', 'l3', 'r3');
}

test('network summary reports the true downline split by leg and status (TEST.md scenario 19)', function () {
    $t = nsTree();

    $summary = app(MemberNetworkSummary::class)->forMember($t['r']);

    expect($summary['left'])->toBe(['total' => 4, 'active' => 3, 'inactive' => 1, 'dummy' => 0, 'store_owners' => 1]);
    expect($summary['right'])->toBe(['total' => 2, 'active' => 1, 'inactive' => 0, 'dummy' => 1, 'store_owners' => 0]);
    expect($summary['team_total'])->toBe(6);
    expect($summary['store_owners'])->toBe(1);
    // Directs are Sponsor-based (L1, R1, R2), never the 2 placement children.
    expect($t['r']->directs()->count())->toBe(3);
});

test('network summary leg totals always equal BinaryTeamSizeCounter for the same member', function () {
    $t = nsTree();

    foreach ($t as $member) {
        $summary = app(MemberNetworkSummary::class)->forMember($member);
        $sides = app(BinaryTeamSizeCounter::class)->countSides($member);

        expect($summary['left']['total'])->toBe($sides['left']);
        expect($summary['right']['total'])->toBe($sides['right']);
    }
});

test('network summary for a mid-tree member and a leaf never counts the member itself', function () {
    $t = nsTree();
    $service = app(MemberNetworkSummary::class);

    $l1 = $service->forMember($t['l1']);
    expect($l1['left']['total'])->toBe(2);
    expect($l1['right']['total'])->toBe(1);

    $leaf = $service->forMember($t['l3']);
    expect($leaf['team_total'])->toBe(0);
    expect($leaf['store_owners'])->toBe(0);
});

test('forMany matches individual forMember results and lists store owners with their side', function () {
    $t = nsTree();
    $service = app(MemberNetworkSummary::class);

    $many = $service->forMany([$t['r']->id, $t['l1']->id, $t['l3']->id]);

    expect($many[$t['r']->id])->toBe($service->forMember($t['r']));
    expect($many[$t['l1']->id])->toBe($service->forMember($t['l1']));
    expect($many[$t['l3']->id]['team_total'])->toBe(0);
    expect($service->forMany([]))->toBe([]);

    expect($service->storeOwnersIn($t['r']))->toBe([
        ['member_id' => $t['r2']->id, 'customer_id' => 'NS-R2', 'name' => $t['r2']->user->name, 'store_name' => 'Owner Jewellers', 'side' => 'left'],
    ]);
    expect($service->storeOwnersIn($t['r1']))->toBe([]);
});

test('Member Management list carries directs/team/position and the Store Owners filter matches only the owner themselves', function () {
    $t = nsTree();
    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)->get('/super-admin/members')->assertInertia(function ($page) {
        $rows = collect($page->toArray()['props']['members']['data'])->keyBy('customer_id');

        expect($rows['NS-R']['directs_count'])->toBe(3);
        expect($rows['NS-R']['team_left'])->toBe(4);
        expect($rows['NS-R']['team_right'])->toBe(2);
        expect($rows['NS-R']['team_total'])->toBe(6);
        expect($rows['NS-R']['placement_side'])->toBeNull();
        expect($rows['NS-L2']['placement_side'])->toBe('left');
        expect($rows['NS-L2']['placement_parent_customer_id'])->toBe('NS-L1');
        expect($rows['NS-R2']['is_store_owner'])->toBeTrue();
        expect($rows['NS-R']['is_store_owner'])->toBeFalse();
        expect($rows->has('NS-R3'))->toBeFalse(); // company dummies are not listed
    });

    $this->actingAs($admin)->get('/super-admin/members?store_owner=1')->assertInertia(function ($page) {
        $ids = collect($page->toArray()['props']['members']['data'])->pluck('customer_id')->all();

        expect($ids)->toBe(['NS-R2']);
    });
});

test('Member Detail exposes the real downline network, Store Owners and a Store Owner flag', function () {
    $t = nsTree();
    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)->get("/super-admin/members/{$t['r']->id}")->assertInertia(fn ($page) => $page
        ->where('member.network.team_total', 6)
        ->where('member.network.left.inactive', 1)
        ->where('member.network.right.dummy', 1)
        ->where('member.direct_count', 3)
        ->where('member.is_store_owner', false)
        ->where('member.store_owners_in_downline.0.customer_id', 'NS-R2')
        ->where('member.store_owners_in_downline.0.store_name', 'Owner Jewellers')
    );

    $this->actingAs($admin)->get("/super-admin/members/{$t['r2']->id}")->assertInertia(fn ($page) => $page->where('member.is_store_owner', true));
});

test('non-Super-Admins cannot reach the Member Management network data', function () {
    $t = nsTree();

    $this->actingAs($t['r']->user)->get('/super-admin/members')->assertForbidden();
    $this->actingAs($t['r']->user)->get("/super-admin/members/{$t['r']->id}")->assertForbidden();
});

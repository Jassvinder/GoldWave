<?php

use App\Models\Member;
use App\Models\User;

/**
 * DOMAIN_LOGIC.md §4.1 (Directs View — Sponsor/Direct only) and §4.2
 * (Tree View — Binary Position only).
 */
function createNetworkMember(string $customerId, ?int $sponsorId = null, ?int $placementParentId = null, ?string $placementSide = null): Member
{
    $user = User::factory()->create(['role' => 'member']);

    return Member::create([
        'user_id' => $user->id,
        'customer_id' => $customerId,
        'sponsor_id' => $sponsorId,
        'placement_parent_id' => $placementParentId,
        'placement_side' => $placementSide,
        'status' => 'active',
        'activated_at' => now(),
    ]);
}

test('Directs View shows the logged-in member\'s own direct members, never Binary Position', function () {
    $root = createNetworkMember('GWL001');
    $direct1 = createNetworkMember('GWL002', sponsorId: $root->id, placementParentId: $root->id, placementSide: 'left');
    // A member placed under $root (binary) but sponsored by someone else — must NOT appear in $root's Directs View.
    $other = createNetworkMember('GWL003');
    createNetworkMember('GWL004', sponsorId: $other->id, placementParentId: $root->id, placementSide: 'right');

    $response = $this->actingAs($root->user)->get('/member/directs')->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->component('member/directs')
        ->where('selectedMember.customer_id', 'GWL001')
        ->has('directs', 1)
        ->where('directs.0.customer_id', 'GWL002')
    );
});

test('Directs View recursive navigation reaches a grandchild in the downline', function () {
    $root = createNetworkMember('GWL010');
    $child = createNetworkMember('GWL011', sponsorId: $root->id);
    $grandchild = createNetworkMember('GWL012', sponsorId: $child->id);

    $this->actingAs($root->user)
        ->get("/member/directs/{$grandchild->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('selectedMember.customer_id', 'GWL012'));
});

test('a member cannot view Directs View for someone outside their own downline', function () {
    $root = createNetworkMember('GWL020');
    $unrelated = createNetworkMember('GWL021');

    $this->actingAs($root->user)
        ->get("/member/directs/{$unrelated->id}")
        ->assertForbidden();
});

test('Super Admin can open Directs View for any member', function () {
    $unrelated = createNetworkMember('GWL030');
    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->get("/member/directs/{$unrelated->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('selectedMember.customer_id', 'GWL030'));
});

test('Tree View shows the Binary Position placement tree, never Sponsor/Direct', function () {
    $root = createNetworkMember('GWL040');
    createNetworkMember('GWL041', sponsorId: null, placementParentId: $root->id, placementSide: 'left');
    // Sponsored by root but NOT placed under root — must not appear in root's Tree View.
    createNetworkMember('GWL042', sponsorId: $root->id);

    $response = $this->actingAs($root->user)->get('/member/tree')->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->component('member/tree')
        ->where('root.customer_id', 'GWL040')
        ->where('root.left.customer_id', 'GWL041')
        ->where('root.right', null)
    );
});

test('a member cannot view Tree View for someone outside their own placement downline', function () {
    $root = createNetworkMember('GWL050');
    $unrelated = createNetworkMember('GWL051');

    $this->actingAs($root->user)
        ->get("/member/tree/{$unrelated->id}")
        ->assertForbidden();
});

test('Super Admin can open Tree View for any member', function () {
    $unrelated = createNetworkMember('GWL060');
    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->get("/member/tree/{$unrelated->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('root.customer_id', 'GWL060'));
});

test('Tree View re-roots when navigating to a placement descendant, showing that member\'s own Left/Right branches', function () {
    $root = createNetworkMember('GWL070');
    $left = createNetworkMember('GWL071', placementParentId: $root->id, placementSide: 'left');
    createNetworkMember('GWL072', placementParentId: $left->id, placementSide: 'left');

    $this->actingAs($root->user)
        ->get("/member/tree/{$left->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('root.customer_id', 'GWL071')
            ->where('root.left.customer_id', 'GWL072')
        );
});

test('guests are redirected away from Directs and Tree views', function () {
    $this->get('/member/directs')->assertRedirect();
    $this->get('/member/tree')->assertRedirect();
});

test('Directs search by Customer ID resolves within the viewer\'s own downline', function () {
    $root = createNetworkMember('GWL090');
    $child = createNetworkMember('GWL091', sponsorId: $root->id);
    $grandchild = createNetworkMember('GWL092', sponsorId: $child->id);

    $this->actingAs($root->user)
        ->get('/member/directs/search?customer_id=GWL092')
        ->assertRedirect("/member/directs/{$grandchild->id}");
});

test('Directs search rejects a Customer ID outside the viewer\'s downline (cross-leg)', function () {
    $root = createNetworkMember('GWL100');
    createNetworkMember('GWL101'); // an unrelated member on a different leg entirely

    $this->actingAs($root->user)
        ->get('/member/directs/search?customer_id=GWL101')
        ->assertRedirect()
        ->assertSessionHasErrors('customer_id');
});

test('Directs search rejects an unknown Customer ID', function () {
    $root = createNetworkMember('GWL110');

    $this->actingAs($root->user)
        ->get('/member/directs/search?customer_id=GWL999')
        ->assertSessionHasErrors('customer_id');
});

test('Tree search by Customer ID resolves within the viewer\'s own placement downline, rejects cross-leg', function () {
    $root = createNetworkMember('GWL120');
    $left = createNetworkMember('GWL121', placementParentId: $root->id, placementSide: 'left');
    $crossLeg = createNetworkMember('GWL122'); // not placed under $root at all

    $this->actingAs($root->user)
        ->get('/member/tree/search?customer_id=GWL121')
        ->assertRedirect("/member/tree/{$left->id}");

    $this->actingAs($root->user)
        ->get('/member/tree/search?customer_id=GWL122')
        ->assertSessionHasErrors('customer_id');
});

test('Super Admin search is not restricted to any particular downline', function () {
    $unrelated = createNetworkMember('GWL130');
    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->get('/member/directs/search?customer_id=GWL130')
        ->assertRedirect("/member/directs/{$unrelated->id}");
});

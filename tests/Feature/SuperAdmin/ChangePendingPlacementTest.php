<?php

use App\Actions\Payments\ApproveCashPayment;
use App\Actions\Registration\RegisterMember;
use App\Actions\Registration\RegisterMemberIds;
use App\Models\Member;
use App\Models\MemberPlacementChange;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

/**
 * T-252 (DOMAIN_LOGIC.md §3.5, Docs/TEST.md scenario 54) — Super Admin moves a not-yet-active member to a new sponsor
 * and/or side, only while nobody else has joined under it.
 */
function cppActive(string $customerId, ?Member $parent = null, ?string $side = null): Member
{
    return Member::create([
        'user_id' => User::factory()->create(['role' => 'member'])->id,
        'customer_id' => $customerId,
        'sponsor_id' => $parent?->id,
        'placement_parent_id' => $parent?->id,
        'placement_side' => $side,
        'status' => 'active',
        'activated_at' => now(),
    ]);
}

function cppJoin(string $sponsor, string $side, string $mobile): Member
{
    return app(RegisterMember::class)([
        'sponsor_code' => $sponsor,
        'placement_side' => $side,
        'gender' => 'male',
        'name' => "Joiner {$mobile}",
        'email' => "{$mobile}@example.test",
        'mobile' => $mobile,
        'membership_plan_id' => MembershipPlan::where('is_active', true)->orderBy('id')->firstOrFail()->id,
        'payment_mode' => 'cash',
    ]);
}

function cppSuperAdmin(): User
{
    return User::factory()->create(['role' => 'super_admin', 'name' => 'Owner']);
}

/** @param  array<string, string>  $data */
function cppMove(Member $member, array $data, ?User $as = null)
{
    return test()->actingAs($as ?? cppSuperAdmin())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from("/super-admin/members/{$member->id}")
        ->post("/super-admin/members/{$member->id}/change-placement", [...['reason' => 'Joined on the wrong side'], ...$data]);
}

beforeEach(function () {
    $this->seed();
    Notification::fake();
    $this->sponsor = cppActive('CPP-S');
});

test('a pending member moves to the other side; the change is recorded and the approval activates it there', function () {
    $member = cppJoin('CPP-S', 'left', '9876533001');

    cppMove($member, ['code' => 'CPP-S', 'placement_side' => 'right'])->assertSessionHasNoErrors();

    $member->refresh();
    expect($member->sponsor_id)->toBe($this->sponsor->id)
        ->and($member->placement_parent_id)->toBe($this->sponsor->id)
        ->and($member->placement_side)->toBe('right');

    $change = MemberPlacementChange::sole();
    expect($change->old_side)->toBe('left')
        ->and($change->new_side)->toBe('right')
        ->and($change->reason)->toBe('Joined on the wrong side');

    // The left place is free again: the next left joining takes it.
    expect(cppJoin('CPP-S', 'left', '9876533002')->placement_parent_id)->toBe($this->sponsor->id);

    app(ApproveCashPayment::class)($member->payments()->sole(), cppSuperAdmin());

    $member->refresh();
    expect($member->status)->toBe('active')
        ->and($member->placement_side)->toBe('right');
});

test('a new sponsor: the member goes to the bottom of the chosen side under them', function () {
    $other = cppActive('CPP-T', $this->sponsor, 'right');
    $otherLeft = cppActive('CPP-TL', $other, 'left');
    $member = cppJoin('CPP-S', 'left', '9876533003');

    cppMove($member, ['code' => 'CPP-T', 'placement_side' => 'left'])->assertSessionHasNoErrors();

    $member->refresh();
    expect($member->sponsor_id)->toBe($other->id)
        ->and($member->placement_parent_id)->toBe($otherLeft->id)
        ->and($member->placement_side)->toBe('left')
        ->and(MemberPlacementChange::sole()->new_sponsor_id)->toBe($other->id);
});

test('refused once someone else has joined under the member, or with an inactive sponsor, or with nothing to change', function () {
    $member = cppJoin('CPP-S', 'left', '9876533004');

    cppMove($member, ['code' => 'NOPE', 'placement_side' => 'right'])->assertSessionHasErrors('code');
    cppMove($member, ['code' => 'CPP-S', 'placement_side' => 'left'])
        ->assertSessionHasErrors(['code' => 'The member is already there. Nothing to change.']);

    // The next left joining under the same sponsor lands under the pending member.
    $below = cppJoin('CPP-S', 'left', '9876533005');
    expect($below->placement_parent_id)->toBe($member->id);

    cppMove($member, ['code' => 'CPP-S', 'placement_side' => 'right'])
        ->assertSessionHasErrors(['code' => 'Someone has already joined under this member, so the sponsor and side can no longer be changed.']);

    // The one below can still move: nobody is under it.
    cppMove($below, ['code' => 'CPP-S', 'placement_side' => 'right'])->assertSessionHasNoErrors();

    expect($member->refresh()->placement_side)->toBe('left')
        ->and(MemberPlacementChange::count())->toBe(1);
});

test('an active member cannot be moved', function () {
    $member = cppJoin('CPP-S', 'left', '9876533006');
    app(ApproveCashPayment::class)($member->payments()->sole(), cppSuperAdmin());

    cppMove($member->refresh(), ['code' => 'CPP-S', 'placement_side' => 'right'])
        ->assertSessionHasErrors(['code' => 'Only a member who is not active yet can be moved.']);
});

test('IDs paid together move together, and only through the first one', function () {
    $ids = app(RegisterMemberIds::class)->forNewPerson([
        'sponsor_code' => 'CPP-S',
        'placement_side' => 'left',
        'gender' => 'female',
        'name' => 'Three Ids',
        'email' => 'three@example.test',
        'mobile' => '9876533007',
        'membership_plan_id' => MembershipPlan::where('is_active', true)->orderBy('id')->firstOrFail()->id,
        'payment_mode' => 'cash',
        'id_count' => 3,
    ]);

    cppMove($ids[1], ['code' => 'CPP-S', 'placement_side' => 'right'])->assertSessionHasErrors('code');

    cppMove($ids[0], ['code' => 'CPP-S', 'placement_side' => 'right'])->assertSessionHasNoErrors();

    $fresh = Member::whereIn('id', $ids->pluck('id'))->orderBy('id')->get();
    expect($fresh[0]->placement_parent_id)->toBe($this->sponsor->id)
        ->and($fresh[0]->placement_side)->toBe('right')
        ->and($fresh[1]->placement_parent_id)->toBe($fresh[0]->id)
        ->and($fresh[2]->placement_parent_id)->toBe($fresh[0]->id)
        ->and($fresh[1]->sponsor_id)->toBe($fresh[0]->id);
});

test('an added ID keeps the first ID as sponsor; only its place changes', function () {
    $first = cppActive('CPP-F', $this->sponsor, 'left');
    $parent = cppActive('CPP-P', $this->sponsor, 'right');
    $added = app(RegisterMemberIds::class)->forExistingPerson($first->user, [
        'id_count' => 1,
        'membership_plan_id' => MembershipPlan::where('is_active', true)->orderBy('id')->firstOrFail()->id,
        'parent_code' => 'CPP-F',
        'placement_side' => 'left',
        'payment_mode' => 'cash',
    ], cppSuperAdmin())->sole();

    cppMove($added, ['code' => 'CPP-P', 'placement_side' => 'right'])->assertSessionHasNoErrors();

    $added->refresh();
    expect($added->sponsor_id)->toBe($first->id)
        ->and($added->placement_parent_id)->toBe($parent->id)
        ->and($added->placement_side)->toBe('right');
});

test('Super Admin only, and a stale password is asked inside the dialog', function () {
    $member = cppJoin('CPP-S', 'left', '9876533008');

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->post("/super-admin/members/{$member->id}/change-placement", ['code' => 'CPP-S', 'placement_side' => 'right', 'reason' => 'Wrong side'])
        ->assertForbidden();

    $this->actingAs(cppSuperAdmin())
        ->from("/super-admin/members/{$member->id}")
        ->post("/super-admin/members/{$member->id}/change-placement", ['code' => 'CPP-S', 'placement_side' => 'right', 'reason' => 'Wrong side'])
        ->assertSessionHasErrors('confirm_password');

    expect($member->refresh()->placement_side)->toBe('left');
});

test('Member Detail offers the form only while the member is pending', function () {
    $member = cppJoin('CPP-S', 'left', '9876533009');
    $admin = cppSuperAdmin();

    $this->actingAs($admin)->get("/super-admin/members/{$member->id}")
        ->assertInertia(fn ($page) => $page
            ->where('change_placement.sponsor_fixed', false)
            ->where('change_placement.blocker', null));

    $this->actingAs($admin)->get("/super-admin/members/{$this->sponsor->id}")
        ->assertInertia(fn ($page) => $page->where('change_placement', null));
});

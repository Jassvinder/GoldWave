<?php

use App\Actions\DummyEntries\AssignDummyEntryToLeader;
use App\Actions\DummyEntries\InsertEntryUnderRoot;
use App\Actions\Payments\ApproveCashPayment;
use App\Actions\Registration\RegisterMember;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\PairEntry;
use App\Models\User;
use App\Services\EarningsVerifier;
use Illuminate\Support\Facades\DB;

/**
 * T-174 — System Maintenance (DOMAIN_LOGIC.md §2, Docs/TEST.md scenario 34): an entry inserted directly under the
 * company root pushes the old child down on the same side and earns only Pair/Reward and Booster.
 */
function mtSuperAdmin(): User
{
    return User::where('role', 'super_admin')->firstOrFail();
}

function mtJoin(string $sponsorCode, string $side, string $name): Member
{
    static $n = 0;
    $n++;

    $member = app(RegisterMember::class)([
        'sponsor_code' => $sponsorCode,
        'placement_side' => $side,
        'gender' => 'male',
        'name' => $name,
        'email' => "mt{$n}@example.test",
        'mobile' => (string) (9500000000 + $n),
        'membership_plan_id' => MembershipPlan::where('code', 'E')->value('id'),
        'payment_mode' => 'cash',
    ]);

    app(ApproveCashPayment::class)($member->payments->firstWhere('type', 'registration'), mtSuperAdmin());

    return $member->fresh();
}

beforeEach(function () {
    $this->seed();
});

test('an insert takes the root\'s slot on that side and pushes the old child down on the same side', function () {
    $root = Member::where('is_company_root', true)->firstOrFail();
    $a = mtJoin($root->customer_id, 'left', 'Member A');

    $x = app(InsertEntryUnderRoot::class)('left');

    expect($x->placement_parent_id)->toBe($root->id);
    expect($x->placement_side)->toBe('left');
    expect($x->sponsor_id)->toBe($root->id);
    expect($x->is_company_dummy)->toBeTrue();
    expect($x->dummy_status)->toBe('unassigned');
    expect($x->benefits_limited)->toBeTrue();
    expect($x->emiSchedule->installments()->where('status', 'paid')->count())->toBe(1);

    $a->refresh();
    expect($a->placement_parent_id)->toBe($x->id);
    expect($a->placement_side)->toBe('left');
    expect($a->sponsor_id)->toBe($root->id); // Sponsor chain untouched.

    // A second insert on the same side forms a line: newest directly under the root.
    $y = app(InsertEntryUnderRoot::class)('left');
    expect($y->placement_parent_id)->toBe($root->id);
    expect($x->fresh()->placement_parent_id)->toBe($y->id);
    expect($x->fresh()->placement_side)->toBe('left');
});

test('an insert on an empty side simply takes that slot', function () {
    $root = Member::where('is_company_root', true)->firstOrFail();

    $x = app(InsertEntryUnderRoot::class)('right');

    expect($x->placement_parent_id)->toBe($root->id);
    expect($x->placement_side)->toBe('right');
    expect(Member::where('placement_parent_id', $x->id)->count())->toBe(0);
});

test('once assigned, the inserted entry earns Pair entries but never Level Income, and the verifier stays clean', function () {
    $root = Member::where('is_company_root', true)->firstOrFail();
    mtJoin($root->customer_id, 'left', 'Member A');

    $this->travel(1)->minutes();
    $x = app(InsertEntryUnderRoot::class)('left');
    $x = app(AssignDummyEntryToLeader::class)($x, 'Inserted Leader', 'inserted@example.test', '9444444444', mtSuperAdmin());

    $this->travel(1)->minutes();
    $b = mtJoin($x->customer_id, 'right', 'Member B');

    // Pair/Reward: B's joining fans out to X on its Right leg.
    expect(PairEntry::where('member_id', $x->id)->where('side', 'right')->count())->toBe(1);

    // Level Income: X is B's direct sponsor but is skipped, and nothing reaches its wallet.
    $level1 = DB::table('income_ledger_calculations')
        ->where('type', 'level_income')
        ->where('source_payment_id', $b->payments()->where('type', 'registration')->value('id'))
        ->where('level_no', 1)
        ->first();
    expect($level1->eligibility_status)->toBe('skipped');
    expect($level1->skip_reason)->toBe('benefits_limited');
    expect(DB::table('wallet_ledger_entries')->where('member_id', $x->id)->count())->toBe(0);

    // Member A joined before X existed, so X correctly has no pair entry from A's joining.
    expect(app(EarningsVerifier::class)->run()['errors'])->toBe(0);
});

test('only a Super Admin can open or use the page', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get('/super-admin/maintenance')->assertForbidden();
    $this->actingAs($admin)->post('/super-admin/maintenance', ['side' => 'left'])->assertForbidden();

    $this->actingAs(mtSuperAdmin())->get('/super-admin/maintenance')
        ->assertOk()->assertInertia(fn ($page) => $page->component('super-admin/maintenance'));

    $this->actingAs(mtSuperAdmin())->post('/super-admin/maintenance', ['side' => 'up'])->assertSessionHasErrors('side');

    $this->actingAs(mtSuperAdmin())->post('/super-admin/maintenance', ['side' => 'right'])
        ->assertRedirect('/super-admin/maintenance');
    expect(Member::where('benefits_limited', true)->count())->toBe(1);
});

<?php

use App\Actions\Settings\PublishRuleVersion;
use App\Models\Member;
use App\Models\PairRewardTransaction;
use App\Models\RuleVersion;
use App\Models\User;
use App\Models\WalletLedgerEntry;

/**
 * T-126 (Wallet: search / sort / pagination) and T-124/T-125 (named Pair/Reward
 * milestones with Reward + Date merged into the one milestone table).
 */
function wpMember(string $customerId): Member
{
    $user = User::factory()->create(['role' => 'member']);

    return Member::create([
        'user_id' => $user->id,
        'customer_id' => $customerId,
        'status' => 'active',
        'activated_at' => now(),
    ]);
}

function wpEntry(Member $member, string $category, string $type, string $amount, ?string $description = null, string $status = 'confirmed', ?string $processedAt = null): WalletLedgerEntry
{
    return WalletLedgerEntry::create([
        'member_id' => $member->id,
        'entry_type' => $type,
        'category' => $category,
        'amount' => $amount,
        'status' => $status,
        'description' => $description,
        'processed_at' => $processedAt ?? now(),
    ]);
}

beforeEach(function () {
    $this->seed();
});

test('the wallet ledger is paginated, newest first by default', function () {
    $member = wpMember('WP-PAGE');

    foreach (range(1, 20) as $i) {
        wpEntry($member, 'level_income', 'credit', (string) $i, "Entry {$i}");
    }

    $this->actingAs($member->user)
        ->get('/member/wallet')
        ->assertInertia(fn ($page) => $page
            ->component('member/wallet')
            ->where('entries.total', 20)
            ->where('entries.per_page', 15)
            ->where('entries.last_page', 2)
            ->where('entries.data.0.description', 'Entry 20')
            ->where('sort', null));

    $this->actingAs($member->user)
        ->get('/member/wallet?page=2')
        ->assertInertia(fn ($page) => $page->has('entries.data', 5));
});

test('wallet search matches category words, description and status, and only within the member\'s own ledger', function () {
    $member = wpMember('WP-SEARCH');
    $other = wpMember('WP-OTHER');
    wpEntry($member, 'level_income', 'credit', '100', 'Level 1 from GWL9');
    wpEntry($member, 'pair_reward', 'credit', '500', 'Milestone 1');
    wpEntry($member, 'payout', 'debit', '200', null, 'pending');
    wpEntry($other, 'level_income', 'credit', '999', 'Someone else');

    $search = fn (string $term) => $this->actingAs($member->user)->get('/member/wallet?search='.urlencode($term));

    $search('level income')->assertInertia(fn ($page) => $page->where('entries.total', 1)->where('entries.data.0.description', 'Level 1 from GWL9'));
    $search('MILESTONE')->assertInertia(fn ($page) => $page->where('entries.total', 1)->where('entries.data.0.category', 'pair_reward'));
    $search('pending')->assertInertia(fn ($page) => $page->where('entries.total', 1)->where('entries.data.0.category', 'payout'));
    $search('someone else')->assertInertia(fn ($page) => $page->where('entries.total', 0));
    $search('')->assertInertia(fn ($page) => $page->where('entries.total', 3));
});

test('wallet columns sort ascending and descending, amount by its signed value', function () {
    $member = wpMember('WP-SORT');
    wpEntry($member, 'level_income', 'credit', '100', 'A');
    wpEntry($member, 'payout', 'debit', '300', 'B');
    wpEntry($member, 'pair_reward', 'credit', '500', 'C');

    $amounts = fn (string $direction) => $this->actingAs($member->user)
        ->get("/member/wallet?sort=amount&direction={$direction}")
        ->viewData('page')['props']['entries']['data'];

    expect(array_column($amounts('desc'), 'description'))->toBe(['C', 'A', 'B']);
    expect(array_column($amounts('asc'), 'description'))->toBe(['B', 'A', 'C']);

    $this->actingAs($member->user)
        ->get('/member/wallet?sort=category&direction=asc')
        ->assertInertia(fn ($page) => $page
            ->where('sort', ['key' => 'category', 'direction' => 'asc'])
            ->where('entries.data.0.category', 'level_income'));
});

test('an unknown wallet sort column is ignored rather than reaching SQL', function () {
    $member = wpMember('WP-BADSORT');
    wpEntry($member, 'level_income', 'credit', '100', 'A');

    $this->actingAs($member->user)
        ->get('/member/wallet?sort='.urlencode('id; DROP TABLE members').'&direction=asc')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('sort', null)->where('entries.total', 1));
});

test('Pair/Reward milestones carry names and each achieved milestone\'s reward and date (T-124, T-125)', function () {
    $member = wpMember('WP-PAIR');

    PairRewardTransaction::create([
        'member_id' => $member->id,
        'milestone_no' => 1,
        'left_consumed_count' => 5,
        'right_consumed_count' => 5,
        'reward_amount' => '500.00',
        'rule_version_id' => RuleVersion::where('is_active', true)->firstOrFail()->id,
        'calculated_for_month' => '2026-09-30',
    ]);

    $this->actingAs($member->user)
        ->get('/member/pair-reward')
        ->assertInertia(fn ($page) => $page
            ->component('member/pair-reward')
            ->missing('rewards')
            ->has('milestones', 15)
            ->where('milestones.0.name', 'Starter')
            ->where('milestones.0.reward_amount', '500.00')
            ->where('milestones.0.achieved_on', '2026-09-30')
            ->where('milestones.1.name', 'Builder')
            ->where('milestones.1.reward_amount', null)
            ->where('milestones.1.achieved_on', null)
            ->where('next_milestone.milestone_no', 2));
});

test('a milestone without a configured name falls back to its number', function () {
    $member = wpMember('WP-PAIR-NONAME');

    app(PublishRuleVersion::class)(
        ['pair_milestones' => [['milestone_no' => 1, 'min_directs' => 2, 'left' => 5, 'right' => 5]]],
        User::where('role', 'super_admin')->firstOrFail(),
    );

    $this->actingAs($member->user)
        ->get('/member/pair-reward')
        ->assertInertia(fn ($page) => $page->where('milestones.0.name', 'Milestone #1'));
});

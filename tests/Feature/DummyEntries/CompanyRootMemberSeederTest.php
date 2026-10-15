<?php

use App\Models\Member;
use App\Models\User;
use Database\Seeders\CompanyRootMemberSeeder;
use Illuminate\Support\Facades\Hash;

/**
 * 15-10-2026 (user request): the root is found by its Customer ID `GWL-ROOT` only, never by an email or mobile, and a
 * re-run never changes an existing root's login — the root's email can be handed to a real member on the server.
 */
test('the root is created once as Shri Shyam with no real email or mobile, and a re-run never touches its login', function () {
    $this->seed();

    $root = Member::where('customer_id', 'GWL-ROOT')->sole();
    expect($root->is_company_root)->toBeTrue()
        ->and($root->placeholder_name)->toBe('Shri Shyam')
        ->and($root->user->name)->toBe('Shri Shyam')
        ->and($root->user->email)->toBe('gwl-root@goldwave.invalid')
        ->and($root->user->mobile)->toBeNull();

    // On the server: the root gets a new email, mobile and password; its old email goes to a real member.
    $root->user->update(['email' => 'root-new@example.com', 'mobile' => '9111111111', 'password' => 'secret-root']);
    $realMember = User::factory()->create(['email' => 'gwl-root@goldwave.invalid', 'name' => 'Real Member']);

    $this->seed(CompanyRootMemberSeeder::class);

    $root->refresh();
    expect(Member::where('is_company_root', true)->count())->toBe(1)
        ->and($root->user->email)->toBe('root-new@example.com')
        ->and($root->user->mobile)->toBe('9111111111')
        ->and(Hash::check('secret-root', $root->user->password))->toBeTrue()
        ->and($realMember->fresh()->name)->toBe('Real Member');
});

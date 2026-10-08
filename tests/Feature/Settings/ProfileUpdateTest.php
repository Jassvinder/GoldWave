<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        // T-215: only Super Admin / company Admin may change their own email here.
        $user = User::factory()->create(['role' => 'super_admin']);

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_a_member_or_store_admin_cannot_change_their_own_email_but_can_change_their_name()
    {
        foreach (['member', 'store_admin'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $original = $user->email;

            $this->actingAs($user)
                ->get(route('profile.edit'))
                ->assertInertia(fn ($page) => $page->where('canChangeEmail', false));

            $this->actingAs($user)
                ->from(route('profile.edit'))
                ->patch(route('profile.update'), [
                    'name' => 'Renamed',
                    'email' => 'someone-else@example.com',
                ])
                ->assertSessionHasErrors(['email' => 'To change your email or mobile number, please visit the GoldWave office.']);

            $this->assertSame($original, $user->refresh()->email);

            // The page no longer submits the email at all; a name-only save still works.
            $this->actingAs($user)
                ->patch(route('profile.update'), ['name' => 'Renamed'])
                ->assertSessionHasNoErrors();

            $this->assertSame('Renamed', $user->refresh()->name);
            $this->assertSame($original, $user->email);
        }
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}

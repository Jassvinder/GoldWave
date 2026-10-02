<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-128 (20-09-2026) — Fortify's generic self-registration is disabled: Super
 * Admin and Admin/Store accounts are never self-registered, and Members join
 * only through GoldWave's own `/join` flow.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generic_registration_screen_is_not_available()
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_generic_registration_cannot_be_posted()
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(404);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_login_page_has_no_sign_up_link_and_still_renders()
    {
        $this->get('/login')->assertOk();
    }
}

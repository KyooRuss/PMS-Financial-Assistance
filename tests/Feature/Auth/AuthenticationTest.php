<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'login' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'login' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_log_in_with_their_username_in_any_letter_case(): void
    {
        User::factory()->create(['username' => 'jdelacruz']);

        $this->post('/login', ['login' => 'JDelaCruz', 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_emails_are_not_case_sensitive(): void
    {
        $user = User::factory()->create(['email' => 'maria@hillside.test']);

        $this->post('/login', ['login' => 'Maria@Hillside.TEST', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_username_or_inactive_account_shows_an_error_under_the_field(): void
    {
        User::factory()->create(['username' => 'jdelacruz', 'is_active' => false]);

        $this->post('/login', ['login' => 'nobody', 'password' => 'password'])
            ->assertSessionHasErrors(['login' => trans('auth.failed')]);
        $this->post('/login', ['login' => 'jdelacruz', 'password' => 'password'])
            ->assertSessionHasErrors(['login' => 'This account is inactive. Please contact LWUA.']);

        $this->assertGuest();
    }

    public function test_login_page_has_the_email_or_username_field_and_no_tagline(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('placeholder="Email or Username"', false)
            ->assertSee('images/LWUAAA.png')
            ->assertSee('Forgot your password? Ask the System Admin to reset it.')
            ->assertDontSee('Reliable Water');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }
}

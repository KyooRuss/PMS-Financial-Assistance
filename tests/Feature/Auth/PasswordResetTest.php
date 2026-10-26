<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * There is no "forgot password": a user who forgets it asks the System Admin to reset it.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_page_has_no_forgot_password_link(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('/forgot-password')
            ->assertSee('Ask the System Admin to reset it.');
    }

    public function test_password_reset_by_email_is_turned_off(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->get('/forgot-password')->assertNotFound();
        $this->post('/forgot-password', ['email' => $user->email])->assertNotFound();
        $this->get('/reset-password/some-token')->assertNotFound();
        $this->post('/reset-password', ['token' => 'x', 'email' => $user->email, 'password' => 'new-password', 'password_confirmation' => 'new-password'])
            ->assertNotFound();

        Notification::assertNotSentTo($user, ResetPassword::class);
    }
}

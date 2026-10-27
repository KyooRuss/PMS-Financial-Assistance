<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Models\WaterDistrict;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function lwdUser(): User
    {
        $district = WaterDistrict::factory()->create(['name' => 'Hillside Water District', 'category' => 'C']);

        return User::factory()->create(['role' => Role::Lwd, 'water_district_id' => $district->id]);
    }

    public function test_pages_tell_the_browser_not_to_keep_a_copy(): void
    {
        $response = $this->actingAs($this->lwdUser())->get('/dashboard');

        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $response->assertHeader('Pragma', 'no-cache');

        // The login page too, so Back after logging in does not show a stale form.
        auth()->logout();
        $this->assertStringContainsString('no-store', $this->get('/login')->headers->get('Cache-Control'));
    }

    public function test_logout_ends_the_session_and_protected_pages_send_you_to_login(): void
    {
        $user = $this->lwdUser();
        $this->post('/login', ['login' => $user->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'You have been logged out.');
        $this->assertGuest();

        // "Back" to any signed-in page asks the server again and is refused.
        foreach (['/dashboard', '/profile', '/admin', '/reports/processing-time'] as $page) {
            $this->get($page)->assertRedirect(route('login'));
        }
    }

    public function test_signed_in_users_cannot_go_back_to_the_login_page(): void
    {
        $this->actingAs($this->lwdUser())->get('/login')->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_a_form_from_an_expired_session_sends_you_to_login(): void
    {
        // Laravel skips the CSRF check while tests run, so raise the same error it would
        // raise for a form from an old page whose security token no longer matches.
        Route::middleware('web')->post('/_stale-form', fn () => throw new TokenMismatchException);

        $this->actingAs($this->lwdUser())
            ->post('/_stale-form')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['login' => 'Your session has expired. Please log in again.']);

        // Signed out with no session at all, the auth middleware already sends them to login.
        auth()->logout();
        $this->post('/logout', ['_token' => 'stale-token'])->assertRedirect(route('login'));
    }
}

<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Only the System Admin has My Profile. Everyone else's name and password are managed by the System Admin.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::SystemAdmin]);
    }

    public function test_profile_page_is_displayed(): void
    {
        $this->actingAs($this->admin())->get('/profile')->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = $this->admin();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test', $user->first_name);
        $this->assertSame('User', $user->last_name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = $this->admin();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_users_cannot_delete_their_own_account(): void
    {
        $user = $this->admin();

        $this->actingAs($user)->get('/profile')->assertDontSee('Delete Account');

        // LWUA manages accounts, so there is no self-delete route.
        $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertMethodNotAllowed();

        $this->assertNotNull($user->fresh());
    }

    public function test_other_users_have_no_profile_page_and_cannot_change_their_name(): void
    {
        foreach ([Role::Lwd, Role::Wsudd, Role::Es, Role::Administrator] as $role) {
            $user = User::factory()->create(['role' => $role, 'first_name' => 'Original']);

            $this->actingAs($user)->get('/profile')->assertForbidden();
            $this->actingAs($user)->patch('/profile', ['first_name' => 'Changed', 'last_name' => 'Name', 'email' => $user->email])
                ->assertForbidden();

            $this->assertSame('Original', $user->fresh()->first_name);
        }
    }

    public function test_only_the_admin_sees_the_my_profile_link(): void
    {
        $this->actingAs($this->admin())->get(route('admin.overview'))->assertSee('My Profile');
        $this->actingAs(User::factory()->create(['role' => Role::Wsudd]))->get('/dashboard')->assertDontSee('My Profile');
        $this->actingAs(User::factory()->create(['role' => Role::Lwd]))->get('/dashboard')->assertDontSee('My Profile');
    }
}

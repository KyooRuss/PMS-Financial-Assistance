<?php

namespace Tests\Feature;

use App\Enums\ProjectType;
use App\Enums\RequirementStage;
use App\Enums\Role;
use App\Models\Requirement;
use App\Models\User;
use Database\Seeders\RequirementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_to_dashboard(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_users_land_on_dashboard_after_login(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_role_middleware_blocks_other_roles(): void
    {
        Route::middleware(['web', 'auth', 'role:lwrd,es'])->get('/_role-test', fn () => 'ok');

        $this->actingAs(User::factory()->create(['role' => Role::Lwd]))
            ->get('/_role-test')->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => Role::Es]))
            ->get('/_role-test')->assertOk();
    }

    public function test_requirement_checklists_match_the_annexes(): void
    {
        $this->seed(RequirementSeeder::class);

        $expected = [
            [ProjectType::RegularAbove50M, [16, 4, 5]],
            [ProjectType::Regular50MBelow, [16, 4, 5]],
            [ProjectType::Emergency, [6, 6, 7]],
        ];

        foreach ($expected as [$type, $counts]) {
            foreach (RequirementStage::cases() as $i => $stage) {
                $this->assertSame($counts[$i], Requirement::checklist($type, $stage)->count(), "{$type->value} / {$stage->value}");
            }
        }

        // Type 1 and Type 2 differ in the years of history required.
        $item8 = fn (ProjectType $type) => Requirement::checklist($type, RequirementStage::PreEvaluation)->where('number', 8)->value('description');
        $this->assertStringStartsWith('5 previous years', $item8(ProjectType::RegularAbove50M));
        $this->assertStringStartsWith('2 previous years', $item8(ProjectType::Regular50MBelow));
    }
}

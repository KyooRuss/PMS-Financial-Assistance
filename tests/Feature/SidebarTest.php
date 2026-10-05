<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Models\Project;
use App\Models\User;
use App\Models\WaterDistrict;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarTest extends TestCase
{
    use RefreshDatabase;

    private function lwdUser(): User
    {
        $district = WaterDistrict::factory()->create(['name' => 'Hillside Water District', 'category' => 'C']);

        return User::factory()->create(['role' => Role::Lwd, 'water_district_id' => $district->id]);
    }

    public function test_system_admin_gets_the_admin_sidebar(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SystemAdmin]))
            ->get(route('admin.overview'))
            ->assertOk()
            ->assertSee('Admin Portal')
            ->assertSeeInOrder(['All Requests', 'Status', 'In Progress', 'Workflow Step', 'WSUDD / UDEV', 'Kept on file', 'Manage', 'Accounts', 'Processing Time'])
            ->assertSee(route('admin.projects.index', ['waiting' => 'cpic']), false)
            ->assertDontSee('Request Project');
    }

    public function test_offices_get_their_queue_sidebar_with_a_count(): void
    {
        $user = $this->lwdUser();
        Project::factory()->for($user)->count(2)->create(['status' => ProjectStatus::Submitted, 'current_step' => 2]);

        $this->actingAs(User::factory()->create(['role' => Role::Wsudd]))
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Office Portal')
            ->assertSeeInOrder(['My Queue', '2', 'Handled by my office', 'Processing Time'])
            ->assertDontSee('Workflow Step')
            ->assertDontSee('Accounts');
    }

    public function test_water_districts_keep_the_top_header(): void
    {
        $this->actingAs($this->lwdUser())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Request Project')
            ->assertDontSee('Office Portal')
            ->assertDontSee('Admin Portal');
    }

    public function test_in_progress_filter_groups_the_active_statuses(): void
    {
        $user = $this->lwdUser();
        Project::factory()->for($user)->create(['title' => 'Active Returned', 'status' => ProjectStatus::Returned]);
        Project::factory()->for($user)->create(['title' => 'Active Approved', 'status' => ProjectStatus::Approved, 'current_step' => 13]);
        Project::factory()->for($user)->create(['title' => 'Closed Completed', 'status' => ProjectStatus::Completed]);
        Project::factory()->for($user)->draft()->create(['title' => 'Just A Draft']);

        $this->actingAs(User::factory()->create(['role' => Role::SystemAdmin]))
            ->get(route('admin.projects.index', ['status' => 'in_progress']))
            ->assertSee('Active Returned')->assertSee('Active Approved')
            ->assertDontSee('Closed Completed')->assertDontSee('Just A Draft');
    }
}

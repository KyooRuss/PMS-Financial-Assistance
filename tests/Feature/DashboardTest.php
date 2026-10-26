<?php

namespace Tests\Feature;

use App\Enums\FundingPurpose;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\Role;
use App\Models\Project;
use App\Models\User;
use App\Models\WaterDistrict;
use App\Support\ProcessFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function lwdUser(string $category = 'C'): User
    {
        $district = WaterDistrict::factory()->create(['name' => "Category {$category} WD", 'category' => $category]);

        return User::factory()->create(['role' => Role::Lwd, 'water_district_id' => $district->id]);
    }

    public function test_lwd_sees_empty_state_without_projects(): void
    {
        $this->actingAs($this->lwdUser())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('You have no projects yet.');
    }

    public function test_lwd_only_sees_their_own_water_districts_projects(): void
    {
        $mine = Project::factory()->for($user = $this->lwdUser())->create();
        $other = Project::factory()->for($this->lwdUser('A'))->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee($mine->reference)
            ->assertDontSee($other->reference);
    }

    public function test_another_districts_project_cannot_be_selected(): void
    {
        $user = $this->lwdUser();
        Project::factory()->for($user)->create();
        $other = Project::factory()->for($this->lwdUser('A'))->create();

        $this->actingAs($user)
            ->get('/dashboard?project='.$other->id)
            ->assertOk()
            ->assertDontSee($other->reference);
    }

    public function test_process_flow_follows_the_selected_project(): void
    {
        $user = $this->lwdUser();
        $regular = Project::factory()->for($user)->create(['current_step' => 3]);
        Project::factory()->for($user)->emergency(FundingPurpose::OperationsAndMaintenance)->create();

        $this->actingAs($user)
            ->get('/dashboard?project='.$regular->id)
            ->assertOk()
            ->assertViewHas('selected', fn (Project $selected) => $selected->is($regular))
            ->assertSee('Rapid assessment of WD-POW')
            ->assertSee('Step 3 out of 15');
    }

    public function test_latest_project_is_selected_by_default(): void
    {
        $user = $this->lwdUser();
        Project::factory()->for($user)->create();
        $latest = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertViewHas('selected', fn (Project $selected) => $selected->is($latest));
    }

    public function test_lwua_offices_get_the_office_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Lwrd]))
            ->get('/dashboard')
            ->assertOk()
            ->assertViewIs('dashboard.office')
            ->assertSee('Loans and Water Rates Department');
    }

    public function test_step_counts_follow_each_process_flow(): void
    {
        $this->assertCount(15, ProcessFlow::steps(ProjectType::RegularAbove50M));
        $this->assertCount(15, ProcessFlow::steps(ProjectType::Regular50MBelow));
        $this->assertCount(13, ProcessFlow::steps(ProjectType::Emergency, FundingPurpose::Capex));
        $this->assertCount(10, ProcessFlow::steps(ProjectType::Emergency, FundingPurpose::OperationsAndMaintenance));
    }

    public function test_completed_project_shows_every_step_done(): void
    {
        $project = Project::factory()->emergency()->make(['status' => ProjectStatus::Completed, 'current_step' => 5]);

        $this->assertSame(13, $project->progressStep());
        $this->assertSame('done', $project->stepState(13));
    }

    public function test_stopped_and_returned_states(): void
    {
        $returned = Project::factory()->make(['status' => ProjectStatus::Returned, 'current_step' => 2]);
        $disapproved = Project::factory()->make(['status' => ProjectStatus::Disapproved, 'current_step' => 11]);

        $this->assertSame('done', $returned->stepState(1));
        $this->assertSame('returned', $returned->stepState(2));
        $this->assertSame('upcoming', $returned->stepState(3));
        $this->assertSame('stopped', $disapproved->stepState(11));
    }
}

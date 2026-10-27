<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\RequirementStage;
use App\Enums\Role;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectLog;
use App\Models\Requirement;
use App\Models\User;
use App\Models\WaterDistrict;
use Database\Seeders\RequirementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Clicking an office on the process-flow stepper shows every file that department uploaded.
 */
class DepartmentFilesTest extends TestCase
{
    use RefreshDatabase;

    private User $waterDistrictUser;

    private User $es;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(RequirementSeeder::class);

        $district = WaterDistrict::factory()->create(['category' => 'C']);
        $this->waterDistrictUser = User::factory()->create(['role' => Role::Lwd, 'water_district_id' => $district->id]);
        $this->es = User::factory()->create(['role' => Role::Es]);
        $this->project = Project::factory()->for($this->waterDistrictUser)->create(['status' => ProjectStatus::UnderEvaluation, 'current_step' => 9]);
    }

    /**
     * Adds a file an office uploaded with its action on the given step.
     */
    private function officeFile(User $office, string $unit, int $step, string $name, bool $internal = false): ProjectDocument
    {
        $log = ProjectLog::factory()->for($this->project)->by($unit, $step)->create(['user_id' => $office->id]);

        return ProjectDocument::factory()->for($this->project)->create([
            'project_log_id' => $log->id,
            'uploaded_by' => $office->id,
            'internal' => $internal,
            'original_name' => $name,
        ]);
    }

    private function waterDistrictFile(string $name): ProjectDocument
    {
        return ProjectDocument::factory()->for($this->project)->create([
            'requirement_id' => Requirement::checklist($this->project->type, RequirementStage::PreEvaluation)->where('number', 1)->value('id'),
            'uploaded_by' => $this->waterDistrictUser->id,
            'original_name' => $name,
        ]);
    }

    public function test_files_are_grouped_by_the_department_that_uploaded_them(): void
    {
        $this->officeFile($this->es, 'ES', 3, 'pow-assessment.pdf', internal: true);
        $this->officeFile($this->es, 'ES', 6, 'lwua-pow.pdf');
        $this->officeFile(User::factory()->create(['role' => Role::Lwrd]), 'LWRD', 4, 'viability.pdf');
        $this->waterDistrictFile('board-resolution.pdf');

        $files = $this->project->fresh()->filesByDepartment(includeInternal: true);

        $this->assertSame(['pow-assessment.pdf', 'lwua-pow.pdf'], $files->get('es')->pluck('original_name')->all());
        $this->assertSame(['viability.pdf'], $files->get('lwrd')->pluck('original_name')->all());
        $this->assertSame(['board-resolution.pdf'], $files->get('lwd')->pluck('original_name')->all());

        $withoutInternal = $this->project->fresh()->filesByDepartment(includeInternal: false);
        $this->assertSame(['lwua-pow.pdf'], $withoutInternal->get('es')->pluck('original_name')->all());
    }

    public function test_office_page_lists_a_departments_files_under_the_step_they_came_from(): void
    {
        $this->officeFile($this->es, 'ES', 3, 'pow-assessment.pdf', internal: true);
        $this->officeFile($this->es, 'ES', 6, 'lwua-pow.pdf');

        $this->actingAs(User::factory()->create(['role' => Role::Lwrd]))
            ->get(route('office.projects.show', $this->project))
            ->assertOk()
            ->assertSee("selectedOffice === 'es'", false)
            ->assertSeeInOrder(['Step 3 · Rapid assessment of WD-POW', 'pow-assessment.pdf', 'Step 6 · Prepare LWUA-POW', 'lwua-pow.pdf'])
            ->assertSee('No files uploaded by CPIC yet.')
            ->assertSee('Show files uploaded by ES')
            ->assertDontSee('Show files uploaded by LWD');
    }

    public function test_water_district_does_not_see_internal_office_files(): void
    {
        $this->officeFile($this->es, 'ES', 3, 'pow-assessment.pdf', internal: true);
        $this->officeFile(User::factory()->create(['role' => Role::Lwrd]), 'LWRD', 9, 'compliance-letter.pdf');

        $this->actingAs($this->waterDistrictUser)
            ->get(route('dashboard', ['project' => $this->project->id]))
            ->assertOk()
            ->assertSee('Files Uploaded')
            ->assertSee('compliance-letter.pdf')
            ->assertDontSee('pow-assessment.pdf');
    }
}

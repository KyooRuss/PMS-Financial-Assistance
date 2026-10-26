<?php

namespace Tests\Feature;

use App\Enums\FundingPurpose;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
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

class ProjectDrawerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(RequirementSeeder::class);
    }

    private function lwdUser(string $category = 'C'): User
    {
        $district = WaterDistrict::factory()->create(['name' => "Category {$category} WD", 'category' => $category]);

        return User::factory()->create(['role' => Role::Lwd, 'water_district_id' => $district->id]);
    }

    private function attach(Project $project, int $number, string $name): void
    {
        ProjectDocument::factory()->for($project)->create([
            'requirement_id' => Requirement::checklist($project->type, RequirementStage::PreEvaluation)->where('number', $number)->value('id'),
            'uploaded_by' => $project->user_id,
            'original_name' => $name,
            'size' => 51200,
        ]);
    }

    public function test_drawer_is_closed_by_default(): void
    {
        Project::factory()->for($this->lwdUser())->create();

        $this->actingAs(User::first())->get('/dashboard')->assertViewHas('drawer', null)->assertDontSee('drawer-title');
    }

    public function test_files_tab_shows_the_correct_annex_and_item_states(): void
    {
        $user = $this->lwdUser();
        $project = Project::factory()->for($user)->create(['type' => ProjectType::Regular50MBelow]);
        $this->attach($project, 1, 'board-resolution.pdf');

        $this->actingAs($user)
            ->get(route('dashboard', ['project' => $project->id, 'drawer' => 'files']))
            ->assertOk()
            ->assertSee('Regular Loan – Within Threshold')
            ->assertDontSee('Annex')
            ->assertSee('board-resolution.pdf')
            ->assertSee('1 of 14 required items uploaded')
            ->assertSee('Missing')
            ->assertSee('Not provided · optional')
            ->assertSee('LWUA Documents')
            ->assertDontSee('>Remove<', false);  // no removing files after submission
    }

    public function test_emergency_om_marks_the_capex_pow_as_not_applicable(): void
    {
        $user = $this->lwdUser();
        $project = Project::factory()->for($user)->emergency(FundingPurpose::OperationsAndMaintenance)->create();

        $this->actingAs($user)
            ->get(route('dashboard', ['project' => $project->id, 'drawer' => 'files']))
            ->assertSee('Not applicable (CAPEX only)');
    }

    public function test_progress_tab_shows_status_and_timeline(): void
    {
        $user = $this->lwdUser();
        $project = Project::factory()->for($user)->create(['status' => ProjectStatus::Returned, 'current_step' => 2]);
        ProjectLog::factory()->for($project)->for($user)->by('LWD', 1)->create(['action' => 'Submitted the request and requirements']);
        ProjectLog::factory()->for($project)->by('WSUDD', 2)->create(['action' => 'Returned the request', 'remarks' => 'Item 16 is not signed.']);

        $this->actingAs($user)
            ->get(route('dashboard', ['project' => $project->id, 'drawer' => 'progress']))
            ->assertOk()
            ->assertSee('Returned – Incomplete')
            ->assertSee('Step 2 of 15 · WSUDD – Completeness check')
            ->assertSee('fix the items and resubmit')
            ->assertSeeInOrder(['Returned the request', 'Item 16 is not signed.', 'Submitted the request and requirements']);
    }

    public function test_submitting_writes_the_first_timeline_entry(): void
    {
        $user = $this->lwdUser();
        $project = Project::factory()->for($user)->draft()->create(['amount' => 30_000_000]);

        foreach ($project->submissionRequirements()->reject->is_optional as $requirement) {
            $this->attach($project, $requirement->number, "item-{$requirement->number}.pdf");
        }

        $this->actingAs($user)->put(route('projects.update', $project), [
            'action' => 'submit', 'amount' => 30_000_000,
        ])->assertSessionHasNoErrors();

        $log = $project->logs()->sole();
        $this->assertSame('LWD', $log->office);
        $this->assertSame(1, $log->step);
        $this->assertTrue($log->user->is($user));
    }

    public function test_drawer_cannot_open_another_districts_project(): void
    {
        $user = $this->lwdUser();
        $mine = Project::factory()->for($user)->create();
        $foreign = Project::factory()->for($this->lwdUser('A'))->create();

        $this->actingAs($user)
            ->get(route('dashboard', ['project' => $foreign->id, 'drawer' => 'files']))
            ->assertOk()
            ->assertViewHas('selected', fn (Project $selected) => $selected->is($mine))
            ->assertDontSee($foreign->reference);
    }

    public function test_unknown_drawer_value_is_ignored(): void
    {
        $user = $this->lwdUser();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('dashboard', ['project' => $project->id, 'drawer' => 'anything']))
            ->assertViewHas('drawer', null);
    }
}

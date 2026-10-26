<?php

namespace Tests\Feature;

use App\Enums\FundingPurpose;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\RequirementStage;
use App\Enums\Role;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\Requirement;
use App\Models\User;
use App\Models\WaterDistrict;
use Database\Seeders\RequirementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectRequestFormTest extends TestCase
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

    private function pdf(string $name = 'document.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 200, 'application/pdf');
    }

    /**
     * One PDF for every required, applicable pre-evaluation item.
     */
    private function requiredFiles(ProjectType $type, ?FundingPurpose $purpose = null): array
    {
        return Requirement::checklist($type, RequirementStage::PreEvaluation)->get()
            ->filter(fn (Requirement $r) => ! $r->is_optional && $r->appliesTo($purpose))
            ->mapWithKeys(fn (Requirement $r) => [$r->id => [$this->pdf("item-{$r->number}.pdf")]])
            ->all();
    }

    private function requirementId(ProjectType $type, int $number): int
    {
        return Requirement::checklist($type, RequirementStage::PreEvaluation)->where('number', $number)->value('id');
    }

    public function test_form_shows_the_checklist(): void
    {
        $this->actingAs($this->lwdUser())
            ->get(route('projects.create', ProjectType::Regular50MBelow))
            ->assertOk()
            ->assertSee('Within Threshold')
            ->assertSeeInOrder(['Back', 'Save as Draft', 'Next', 'Submit'])
            ->assertSee('Board Resolution requesting Financial Assistance.')
            ->assertSee("2 previous years' MDS")
            ->assertSee('Certification of water source')
            ->assertSee('chosen = [...$event.target.files]', false);
    }

    public function test_draft_can_be_saved_with_only_a_title(): void
    {
        $user = $this->lwdUser();
        $itemOne = $this->requirementId(ProjectType::Regular50MBelow, 1);

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'type' => ProjectType::Regular50MBelow->value,
            'action' => 'draft',
            'title' => 'Pipeline Rehabilitation',
            'documents' => [$itemOne => [$this->pdf()]],
        ]);

        $project = Project::sole();
        $response->assertRedirect(route('projects.edit', $project));

        $this->assertSame(ProjectStatus::Draft, $project->status);
        $this->assertSame($user->water_district_id, $project->water_district_id);
        $this->assertCount(1, $project->documents);
        Storage::disk('local')->assertExists($project->documents->first()->path);
    }

    public function test_submit_requires_every_required_item(): void
    {
        $this->actingAs($this->lwdUser())->post(route('projects.store'), [
            'type' => ProjectType::Regular50MBelow->value,
            'action' => 'submit',
            'title' => 'Pipeline Rehabilitation',
            'amount' => 30_000_000,
            'documents' => [$this->requirementId(ProjectType::Regular50MBelow, 1) => [$this->pdf()]],
        ])->assertSessionHasErrors([
            'documents.'.$this->requirementId(ProjectType::Regular50MBelow, 2),
            'documents.'.$this->requirementId(ProjectType::Regular50MBelow, 16),
        ])->assertSessionDoesntHaveErrors([
            // Items 10 and 11 are "if applicable" / "if any".
            'documents.'.$this->requirementId(ProjectType::Regular50MBelow, 10),
            'documents.'.$this->requirementId(ProjectType::Regular50MBelow, 11),
        ]);

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_complete_request_is_submitted_to_wsudd(): void
    {
        $this->actingAs($this->lwdUser())->post(route('projects.store'), [
            'type' => ProjectType::Regular50MBelow->value,
            'action' => 'submit',
            'title' => 'Pipeline Rehabilitation',
            'amount' => 30_000_000,
            'documents' => $this->requiredFiles(ProjectType::Regular50MBelow),
        ])->assertSessionHasNoErrors();

        $project = Project::sole();
        $this->assertSame(ProjectStatus::Submitted, $project->status);
        $this->assertSame(2, $project->current_step);
        $this->assertNotNull($project->submitted_at);
        $this->assertCount(14, $project->documents);
    }

    public function test_amount_must_match_the_project_type(): void
    {
        $user = $this->lwdUser();

        $this->actingAs($user)->post(route('projects.store'), [
            'type' => ProjectType::Regular50MBelow->value, 'action' => 'draft', 'title' => 'X', 'amount' => 60_000_000,
        ])->assertSessionHasErrors('amount');

        $this->actingAs($user)->post(route('projects.store'), [
            'type' => ProjectType::RegularAbove50M->value, 'action' => 'draft', 'title' => 'X', 'amount' => 50_000_000,
        ])->assertSessionHasErrors('amount');

        $this->actingAs($user)->post(route('projects.store'), [
            'type' => ProjectType::Regular50MBelow->value, 'action' => 'draft', 'title' => 'X', 'amount' => 50_000_000,
        ])->assertSessionHasNoErrors();
    }

    public function test_emergency_om_does_not_require_the_capex_pow(): void
    {
        $this->actingAs($this->lwdUser('C'))->post(route('projects.store'), [
            'type' => ProjectType::Emergency->value,
            'action' => 'submit',
            'title' => 'Drought Response',
            'amount' => 5_000_000,
            'funding_purpose' => FundingPurpose::OperationsAndMaintenance->value,
            'documents' => $this->requiredFiles(ProjectType::Emergency, FundingPurpose::OperationsAndMaintenance),
        ])->assertSessionHasNoErrors();

        $this->assertSame(FundingPurpose::OperationsAndMaintenance, Project::sole()->funding_purpose);
        $this->assertCount(10, Project::sole()->steps());
    }

    public function test_emergency_submit_requires_a_funding_purpose(): void
    {
        $this->actingAs($this->lwdUser('C'))->post(route('projects.store'), [
            'type' => ProjectType::Emergency->value,
            'action' => 'submit',
            'title' => 'Typhoon Damage',
            'amount' => 5_000_000,
            'documents' => $this->requiredFiles(ProjectType::Emergency, FundingPurpose::Capex),
        ])->assertSessionHasErrors('funding_purpose');
    }

    public function test_category_a_cannot_submit_an_emergency_request(): void
    {
        $this->actingAs($this->lwdUser('A'))->post(route('projects.store'), [
            'type' => ProjectType::Emergency->value, 'action' => 'draft', 'title' => 'X',
        ])->assertForbidden();
    }

    public function test_only_pdfs_are_accepted(): void
    {
        $itemOne = $this->requirementId(ProjectType::Regular50MBelow, 1);

        $this->actingAs($this->lwdUser())->post(route('projects.store'), [
            'type' => ProjectType::Regular50MBelow->value,
            'action' => 'draft',
            'title' => 'X',
            'documents' => [$itemOne => [UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg')]],
        ])->assertSessionHasErrors("documents.{$itemOne}.0");
    }

    public function test_uploads_must_belong_to_the_projects_checklist(): void
    {
        $emergencyItem = $this->requirementId(ProjectType::Emergency, 1);

        $this->actingAs($this->lwdUser())->post(route('projects.store'), [
            'type' => ProjectType::Regular50MBelow->value,
            'action' => 'draft',
            'title' => 'X',
            'documents' => [$emergencyItem => [$this->pdf()]],
        ])->assertSessionHasErrors('documents');
    }

    public function test_draft_can_be_continued_and_files_removed(): void
    {
        $user = $this->lwdUser();
        $project = Project::factory()->for($user)->draft()->create();
        $old = ProjectDocument::factory()->for($project)->create([
            'requirement_id' => $this->requirementId(ProjectType::Regular50MBelow, 1),
            'uploaded_by' => $user->id,
            'original_name' => 'old.pdf',
        ]);

        $this->actingAs($user)->get(route('projects.edit', $project))->assertOk()->assertSee('old.pdf');

        $this->actingAs($user)->put(route('projects.update', $project), [
            'action' => 'draft',
            'title' => 'Renamed Project',
            'remove_documents' => [$old->id],
            'documents' => [$this->requirementId(ProjectType::Regular50MBelow, 2) => [$this->pdf('new.pdf')]],
        ])->assertRedirect(route('projects.edit', $project));

        $project->refresh();
        $this->assertSame('Renamed Project', $project->title);
        $this->assertSame(['new.pdf'], $project->documents()->pluck('original_name')->all());
        Storage::disk('local')->assertMissing($old->path);
    }

    public function test_submitting_a_draft_counts_files_already_saved(): void
    {
        $user = $this->lwdUser();
        $project = Project::factory()->for($user)->draft()->create(['amount' => 30_000_000]);

        foreach (array_keys($this->requiredFiles(ProjectType::Regular50MBelow)) as $requirementId) {
            ProjectDocument::factory()->for($project)->create(['requirement_id' => $requirementId, 'uploaded_by' => $user->id]);
        }

        $this->actingAs($user)->put(route('projects.update', $project), [
            'action' => 'submit',
            'title' => $project->title,
            'amount' => 30_000_000,
        ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard', ['project' => $project->id]));

        $this->assertSame(ProjectStatus::Submitted, $project->fresh()->status);
    }

    public function test_submitted_or_foreign_projects_cannot_be_edited(): void
    {
        $user = $this->lwdUser();
        $submitted = Project::factory()->for($user)->create(['status' => ProjectStatus::Submitted]);
        $foreignDraft = Project::factory()->for($this->lwdUser('A'))->draft()->create();

        $this->actingAs($user)->get(route('projects.edit', $submitted))->assertForbidden();
        $this->actingAs($user)->get(route('projects.edit', $foreignDraft))->assertForbidden();
        $this->actingAs($user)->put(route('projects.update', $foreignDraft), ['action' => 'draft', 'title' => 'Hijack'])->assertForbidden();
    }

    public function test_documents_are_only_visible_to_the_owner_and_lwua(): void
    {
        $owner = $this->lwdUser();
        $project = Project::factory()->for($owner)->draft()->create();
        $document = ProjectDocument::factory()->for($project)->create([
            'requirement_id' => $this->requirementId(ProjectType::Regular50MBelow, 1),
            'uploaded_by' => $owner->id,
        ]);

        $this->actingAs($owner)->get(route('documents.show', $document))->assertOk();
        $this->actingAs(User::factory()->create(['role' => Role::Wsudd]))->get(route('documents.show', $document))->assertOk();
        $this->actingAs($this->lwdUser('A'))->get(route('documents.show', $document))->assertForbidden();
    }
}

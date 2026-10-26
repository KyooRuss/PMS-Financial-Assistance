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

class CompletenessCheckTest extends TestCase
{
    use RefreshDatabase;

    private User $lwd;

    private User $wsudd;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(RequirementSeeder::class);

        $district = WaterDistrict::factory()->create(['name' => 'Hillside Water District', 'category' => 'C']);
        $this->lwd = User::factory()->create(['role' => Role::Lwd, 'water_district_id' => $district->id]);
        $this->wsudd = User::factory()->create(['role' => Role::Wsudd]);
    }

    private function submittedProject(ProjectType $type = ProjectType::Regular50MBelow, ?FundingPurpose $purpose = null): Project
    {
        $project = Project::factory()->for($this->lwd)->draft()->create([
            'type' => $type, 'funding_purpose' => $purpose, 'amount' => 20_000_000,
        ]);

        foreach ($project->submissionRequirements()->reject->is_optional as $requirement) {
            $this->upload($project, $requirement->number);
        }

        $this->actingAs($this->lwd)->put(route('projects.update', $project), [
            'action' => 'submit', 'amount' => 20_000_000,
            'funding_purpose' => $purpose?->value,
        ])->assertSessionHasNoErrors();

        return $project->fresh();
    }

    private function upload(Project $project, int $number): void
    {
        ProjectDocument::factory()->for($project)->create([
            'requirement_id' => $this->requirementId($project, $number),
            'uploaded_by' => $this->lwd->id,
            'original_name' => "item-{$number}.pdf",
        ]);
    }

    private function requirementId(Project $project, int $number): int
    {
        return Requirement::checklist($project->type, RequirementStage::PreEvaluation)->where('number', $number)->value('id');
    }

    public function test_submitted_request_appears_in_the_wsudd_queue_only(): void
    {
        $project = $this->submittedProject();

        $this->assertSame(Role::Wsudd, $project->current_role);

        $this->actingAs($this->wsudd)->get('/dashboard')
            ->assertOk()->assertSee('My Queue')->assertSee($project->reference)->assertSee('Hillside Water District');

        $this->actingAs(User::factory()->create(['role' => Role::Es]))->get('/dashboard')
            ->assertDontSee($project->reference);
    }

    public function test_wsudd_sees_the_review_page_with_actions(): void
    {
        $project = $this->submittedProject();

        $this->actingAs($this->wsudd)->get(route('office.projects.show', $project))
            ->assertOk()
            ->assertSee('Submitted Requirements')
            ->assertSee('Flag for return')
            ->assertSee('Complete – Endorse')
            ->assertSee('Return to Water District');
    }

    public function test_other_offices_can_view_but_not_act(): void
    {
        $project = $this->submittedProject();
        $es = User::factory()->create(['role' => Role::Es]);

        $this->actingAs($es)->get(route('office.projects.show', $project))
            ->assertOk()
            ->assertSee('Waiting on WSUDD / UDEV')
            ->assertDontSee('Flag for return');

        $this->actingAs($es)->post(route('office.projects.endorse', $project))->assertForbidden();
        $this->actingAs($this->lwd)->get(route('office.projects.show', $project))->assertForbidden();
    }

    public function test_endorse_moves_a_regular_request_to_es(): void
    {
        $project = $this->submittedProject();

        $this->actingAs($this->wsudd)->post(route('office.projects.endorse', $project), ['remarks' => 'All complete.'])
            ->assertRedirect(route('dashboard'));

        $project->refresh();
        $this->assertSame(ProjectStatus::UnderEvaluation, $project->status);
        $this->assertSame(3, $project->current_step);
        $this->assertSame(Role::Es, $project->current_role);
        $this->assertStringContainsString('Endorsed items 1–11 to LWRD', $project->logs()->first()->action);
    }

    public function test_endorse_moves_an_emergency_request_to_cpic(): void
    {
        $project = $this->submittedProject(ProjectType::Emergency, FundingPurpose::Capex);

        $this->actingAs($this->wsudd)->post(route('office.projects.endorse', $project));

        $project->refresh();
        $this->assertSame(3, $project->current_step);
        $this->assertSame(Role::Cpic, $project->current_role);
    }

    public function test_cannot_endorse_with_missing_required_items(): void
    {
        $project = $this->submittedProject();
        $project->documents()->where('requirement_id', $this->requirementId($project, 16))->delete();

        $this->actingAs($this->wsudd)->post(route('office.projects.endorse', $project))
            ->assertSessionHas('error');

        $this->assertSame(ProjectStatus::Submitted, $project->fresh()->status);
    }

    public function test_return_needs_at_least_one_flagged_item_with_remarks(): void
    {
        $project = $this->submittedProject();
        $item = $this->requirementId($project, 16);

        $this->actingAs($this->wsudd)->post(route('office.projects.return', $project), ['items' => []])
            ->assertSessionHasErrors('items');

        $this->actingAs($this->wsudd)->post(route('office.projects.return', $project), ['items' => [$item => ['flag' => '1', 'remarks' => '']]])
            ->assertSessionHasErrors("items.{$item}.remarks");

        $this->assertSame(ProjectStatus::Submitted, $project->fresh()->status);
    }

    public function test_full_return_and_resubmit_loop(): void
    {
        $project = $this->submittedProject();
        $item16 = $this->requirementId($project, 16);

        // WSUDD returns it with a remark on item 16.
        $this->actingAs($this->wsudd)->post(route('office.projects.return', $project), [
            'remarks' => 'Please check item 16.',
            'items' => [$item16 => ['flag' => '1', 'remarks' => 'Certification is not signed.']],
        ])->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame(ProjectStatus::Returned, $project->status);
        $this->assertSame(Role::Lwd, $project->current_role);

        // The LWD sees the return on the dashboard, the drawer and the form.
        $this->actingAs($this->lwd)->get('/dashboard')->assertSee('Fix &amp; Resubmit', false);
        $this->actingAs($this->lwd)->get(route('dashboard', ['project' => $project->id, 'drawer' => 'progress']))
            ->assertSee('Certification is not signed.')->assertSee('Please check item 16.');
        $this->actingAs($this->lwd)->get(route('projects.edit', $project))
            ->assertOk()->assertSee('To fix:')->assertSee('Certification is not signed.')->assertSee('Resubmit');

        // Resubmitting without a new file for item 16 is refused.
        $this->actingAs($this->lwd)->put(route('projects.update', $project), [
            'action' => 'submit', 'amount' => 20_000_000,
        ])->assertSessionHasErrors("documents.{$item16}");

        // With a corrected file it goes back to WSUDD, and the return is resolved.
        $this->actingAs($this->lwd)->put(route('projects.update', $project), [
            'action' => 'submit', 'amount' => 20_000_000,
            'documents' => [$item16 => [UploadedFile::fake()->create('signed-certification.pdf', 30, 'application/pdf')]],
        ])->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame(ProjectStatus::Submitted, $project->status);
        $this->assertSame(Role::Wsudd, $project->current_role);
        $this->assertSame(0, $project->returnedItems()->open()->count());
        $this->assertSame('Resubmitted the request with corrections', $project->logs()->first()->action);

        // WSUDD sees the resubmission and endorses it.
        $this->actingAs($this->wsudd)->get('/dashboard')->assertSee('Resubmitted after a return');
        $this->actingAs($this->wsudd)->post(route('office.projects.endorse', $project));
        $this->assertSame(ProjectStatus::UnderEvaluation, $project->fresh()->status);
    }

    public function test_handled_tab_lists_projects_the_office_acted_on(): void
    {
        $project = $this->submittedProject();
        $this->actingAs($this->wsudd)->post(route('office.projects.endorse', $project));

        $this->actingAs($this->wsudd)->get(route('dashboard', ['tab' => 'handled']))
            ->assertOk()->assertSee($project->reference)->assertSee('Under Evaluation');

        $this->actingAs($this->wsudd)->get('/dashboard')->assertSee('Nothing is waiting for your office right now.');
    }
}

<?php

namespace Tests\Feature;

use App\Enums\FundingPurpose;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\Role;
use App\Models\Project;
use App\Models\User;
use App\Models\WaterDistrict;
use Database\Seeders\RequirementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProgramOfWorkTest extends TestCase
{
    use RefreshDatabase;

    private User $lwd;

    private User $es;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(RequirementSeeder::class);

        $district = WaterDistrict::factory()->create(['name' => 'Hillside Water District', 'category' => 'C']);
        $this->lwd = User::factory()->create(['role' => Role::Lwd, 'water_district_id' => $district->id]);
        $this->es = User::factory()->create(['role' => Role::Es]);
        $this->administrator = User::factory()->create(['role' => Role::Administrator]);
    }

    private function projectAt(int $step, ProjectType $type = ProjectType::Regular50MBelow, ?FundingPurpose $purpose = null): Project
    {
        return Project::factory()->for($this->lwd)->create([
            'type' => $type, 'funding_purpose' => $purpose,
            'status' => ProjectStatus::UnderEvaluation, 'current_step' => $step,
        ]);
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->create($name, 60, 'application/pdf');
    }

    public function test_full_pow_path_with_a_revision(): void
    {
        $project = $this->projectAt(6);

        // Step 6: ES prepares and submits the LWUA-POW.
        $this->actingAs($this->es)->get(route('office.projects.show', $project))->assertSee('Submit to Administrator');
        $this->actingAs($this->es)->post(route('office.projects.pow.prepare', $project), ['lwua_pow' => $this->pdf('lwua-pow-v1.pdf')])
            ->assertSessionHasNoErrors();
        $this->assertSame(Role::Administrator, $project->fresh()->current_role);

        // Step 7: the Administrator sees the file and returns it.
        $this->actingAs($this->administrator)->get(route('office.projects.show', $project))
            ->assertSee('LWUA-POW for approval')->assertSee('lwua-pow-v1.pdf')->assertSee('Return to ES for Revision');

        $this->actingAs($this->administrator)->post(route('office.projects.pow.return', $project), ['remarks' => 'Add the contingency breakdown.'])
            ->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame(6, $project->current_step);
        $this->assertSame(Role::Es, $project->current_role);
        $this->assertSame(ProjectStatus::UnderEvaluation, $project->status);

        // Back at step 6, ES sees the Administrator's remarks and resubmits.
        $this->actingAs($this->es)->get(route('office.projects.show', $project))
            ->assertSee('Returned by the Administrator for revision')->assertSee('Add the contingency breakdown.');
        $this->actingAs($this->es)->post(route('office.projects.pow.prepare', $project), ['lwua_pow' => $this->pdf('lwua-pow-v2.pdf')]);

        // The Administrator now sees the revised file and approves.
        $this->actingAs($this->administrator)->get(route('office.projects.show', $project))->assertSee('lwua-pow-v2.pdf');
        $this->actingAs($this->administrator)->post(route('office.projects.pow.approve', $project), ['remarks' => 'Approved.'])
            ->assertSessionHasNoErrors();
        $this->assertSame(8, $project->fresh()->current_step);

        // Step 8: ES uploads the Approved POW and forwards to LWRD.
        $this->actingAs($this->es)->get(route('office.projects.show', $project))->assertSee('The Administrator approved the LWUA-POW');
        $this->actingAs($this->es)->post(route('office.projects.pow.forward', $project), ['approved_pow' => $this->pdf('approved-pow.pdf')])
            ->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame(9, $project->current_step);
        $this->assertSame('financial_evaluation', $project->currentStep()['key']);
        $this->assertSame(Role::Lwrd, $project->current_role);
    }

    public function test_approved_pow_is_an_lwua_document_the_water_district_can_see(): void
    {
        $project = $this->projectAt(8);

        $this->actingAs($this->es)->post(route('office.projects.pow.forward', $project), ['approved_pow' => $this->pdf('approved-pow.pdf')]);

        $document = $project->documents()->sole();
        $this->assertFalse($document->isInternal());
        $this->assertSame('Approved Program of Work with Project Profile', $document->requirement->description);

        $this->actingAs($this->lwd)->get(route('dashboard', ['project' => $project->id, 'drawer' => 'files']))
            ->assertSee('approved-pow.pdf');
        $this->actingAs($this->lwd)->get(route('documents.show', $document))->assertOk();
    }

    public function test_lwua_pow_draft_stays_internal(): void
    {
        $project = $this->projectAt(6);
        $this->actingAs($this->es)->post(route('office.projects.pow.prepare', $project), ['lwua_pow' => $this->pdf('lwua-pow-draft.pdf')]);

        $this->assertTrue($project->documents()->sole()->isInternal());
        $this->actingAs($this->lwd)->get(route('dashboard', ['project' => $project->id, 'drawer' => 'progress']))
            ->assertDontSee('lwua-pow-draft.pdf');
    }

    public function test_files_and_return_remarks_are_required(): void
    {
        $project = $this->projectAt(6);
        $this->actingAs($this->es)->post(route('office.projects.pow.prepare', $project), [])->assertSessionHasErrors('lwua_pow');

        $atApproval = $this->projectAt(7);
        $this->actingAs($this->administrator)->post(route('office.projects.pow.return', $atApproval), ['remarks' => ''])
            ->assertSessionHasErrors('remarks');
        $this->assertSame(7, $atApproval->fresh()->current_step);

        $atForward = $this->projectAt(8);
        $this->actingAs($this->es)->post(route('office.projects.pow.forward', $atForward), [])->assertSessionHasErrors('approved_pow');
    }

    public function test_emergency_capex_follows_the_same_steps(): void
    {
        $project = $this->projectAt(4, ProjectType::Emergency, FundingPurpose::Capex);

        $this->actingAs($this->es)->post(route('office.projects.pow.prepare', $project), ['lwua_pow' => $this->pdf('pow.pdf')]);
        $this->actingAs($this->administrator)->post(route('office.projects.pow.approve', $project));
        $this->actingAs($this->es)->post(route('office.projects.pow.forward', $project), ['approved_pow' => $this->pdf('approved.pdf')]);

        $project->refresh();
        $this->assertSame(7, $project->current_step);
        $this->assertSame('financial_evaluation', $project->currentStep()['key']);
        $this->assertSame(Role::Lwrd, $project->current_role);
    }

    public function test_wrong_office_or_wrong_step_is_refused(): void
    {
        $atPrepare = $this->projectAt(6);
        $atAssessment = $this->projectAt(3); // ES's turn, but a different step

        $this->actingAs($this->administrator)->post(route('office.projects.pow.approve', $atPrepare))->assertForbidden();
        $this->actingAs($this->es)->post(route('office.projects.pow.prepare', $atAssessment), ['lwua_pow' => $this->pdf('x.pdf')])->assertForbidden();
        $this->actingAs($this->es)->post(route('office.projects.pow.forward', $atPrepare), ['approved_pow' => $this->pdf('x.pdf')])->assertForbidden();
    }
}

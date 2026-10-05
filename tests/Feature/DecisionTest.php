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

class DecisionTest extends TestCase
{
    use RefreshDatabase;

    private User $lwd;

    private User $administrator;

    private User $lwrd;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(RequirementSeeder::class);

        $district = WaterDistrict::factory()->create(['name' => 'Hillside Water District', 'category' => 'C']);
        $this->lwd = User::factory()->create(['role' => Role::Lwd, 'water_district_id' => $district->id]);
        $this->administrator = User::factory()->create(['role' => Role::Administrator]);
        $this->lwrd = User::factory()->create(['role' => Role::Lwrd]);
    }

    private function projectAt(int $step, ProjectStatus $status = ProjectStatus::UnderEvaluation, ProjectType $type = ProjectType::Regular50MBelow, ?FundingPurpose $purpose = null): Project
    {
        return Project::factory()->for($this->lwd)->create([
            'type' => $type, 'funding_purpose' => $purpose, 'status' => $status, 'current_step' => $step,
        ]);
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->create($name, 30, 'application/pdf');
    }

    public function test_approval_path_to_a_completed_contract(): void
    {
        $project = $this->projectAt(12);
        $lpmd = User::factory()->create(['role' => Role::Lpmd]);
        $legal = User::factory()->create(['role' => Role::Legal]);

        // Step 12: the Administrator approves with the signed memorandum.
        $this->actingAs($this->administrator)->get(route('office.projects.show', $project))->assertSee('Approve')->assertSee('Disapprove');
        $this->actingAs($this->administrator)->post(route('office.projects.approve', $project), [
            'remarks' => 'Approved.', 'signed_memorandum' => $this->pdf('signed-memo.pdf'),
        ])->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame(ProjectStatus::Approved, $project->status);
        $this->assertSame('notify', $project->currentStep()['key']);
        $this->assertSame(Role::Lwrd, $project->current_role);
        $memo = $project->documents()->sole();
        $this->assertSame('Approved Loan per Memorandum for the Administrator', $memo->requirement->description);
        $this->assertFalse($memo->isInternal());

        // Step 13: LWRD sends the approval letter; the project stays Approved.
        $this->actingAs($this->lwrd)->post(route('office.projects.approval-letter', $project), [
            'letter' => $this->pdf('approval-letter.pdf'), 'mfta' => $this->pdf('approved-mfta.pdf'),
        ])->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame(ProjectStatus::Approved, $project->status);
        $this->assertSame(Role::Lpmd, $project->current_role);

        // Step 14: LPMD must confirm the documents, then endorses to Legal.
        $this->actingAs($lpmd)->post(route('office.projects.endorse-to-legal', $project), [])->assertSessionHasErrors('documents_secured');
        $this->actingAs($lpmd)->post(route('office.projects.endorse-to-legal', $project), ['documents_secured' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(Role::Legal, $project->fresh()->current_role);

        // Step 15: Legal uploads the contract; the request is completed.
        $this->actingAs($legal)->post(route('office.projects.contract', $project), ['contract' => $this->pdf('fac.pdf')])->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame(ProjectStatus::Completed, $project->status);
        $this->assertNull($project->current_role);
        $this->assertSame(15, $project->progressStep());
        $this->assertSame('done', $project->stepState(15));

        // The water district sees the letter and the contract, not the internal MFTA.
        $this->actingAs($this->lwd)->get('/dashboard')->assertSee('View Project');
        $this->actingAs($this->lwd)->get(route('dashboard', ['project' => $project->id, 'drawer' => 'progress']))
            ->assertSee('approval-letter.pdf')
            ->assertSee('fac.pdf')
            ->assertDontSee('approved-mfta.pdf')
            ->assertSee('The Financial Assistance Contract has been prepared.');
    }

    public function test_disapproval_waits_for_lwrds_letter_then_closes(): void
    {
        $project = $this->projectAt(12);

        $this->actingAs($this->administrator)->post(route('office.projects.disapprove', $project), ['remarks' => ''])
            ->assertSessionHasErrors('remarks');

        $this->actingAs($this->administrator)->post(route('office.projects.disapprove', $project), ['remarks' => 'Coverage too low.'])
            ->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame(ProjectStatus::Disapproved, $project->status);
        $this->assertSame(Role::Lwrd, $project->current_role);
        $this->assertSame('stopped', $project->stepState(12));
        $this->actingAs($this->lwd)->get(route('dashboard', ['project' => $project->id, 'drawer' => 'progress']))
            ->assertSee('LWRD will send you a letter.');

        // LWRD sees it in the queue and sends the letter.
        $this->actingAs($this->lwrd)->get('/dashboard')->assertSee($project->reference);
        $this->actingAs($this->lwrd)->get(route('office.projects.show', $project))
            ->assertSee('The Administrator disapproved this request')->assertSee('Coverage too low.')->assertSee('Send Letter &amp; Close', false);

        $this->actingAs($this->lwrd)->post(route('office.projects.disapproval-letter', $project), ['letter' => $this->pdf('disapproval-letter.pdf')])
            ->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertNull($project->current_role);
        $this->assertNotNull($project->disapproval_letter_sent_at);
        $this->assertSame('LWRD', $project->logs()->first()->office);

        $this->actingAs($this->lwd)->get(route('dashboard', ['project' => $project->id, 'drawer' => 'progress']))
            ->assertSee('disapproval-letter.pdf')->assertSee('letter is in the timeline');
        $this->actingAs($this->lwrd)->post(route('office.projects.disapproval-letter', $project), ['letter' => $this->pdf('again.pdf')])
            ->assertForbidden();
    }

    public function test_emergency_om_uses_the_same_decision_steps(): void
    {
        $project = $this->projectAt(7, type: ProjectType::Emergency, purpose: FundingPurpose::OperationsAndMaintenance);
        $this->assertSame('final_decision', $project->currentStep()['key']);

        $this->actingAs($this->administrator)->post(route('office.projects.approve', $project))->assertSessionHasNoErrors();

        $this->assertSame('notify', $project->fresh()->currentStep()['key']);
        $this->assertSame(8, $project->fresh()->current_step);
    }

    public function test_steps_are_guarded(): void
    {
        $atDecision = $this->projectAt(12);
        $atNotify = $this->projectAt(13, ProjectStatus::Approved);

        $this->actingAs($this->lwrd)->post(route('office.projects.approval-letter', $atDecision), ['letter' => $this->pdf('x.pdf')])->assertForbidden();
        $this->actingAs($this->administrator)->post(route('office.projects.approve', $atNotify))->assertForbidden();
        $this->actingAs($this->lwrd)->post(route('office.projects.disapproval-letter', $atNotify), ['letter' => $this->pdf('x.pdf')])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => Role::Legal]))
            ->post(route('office.projects.contract', $atNotify), ['contract' => $this->pdf('x.pdf')])->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Enums\FundingPurpose;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\RequirementStage;
use App\Enums\Role;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use App\Models\WaterDistrict;
use Database\Seeders\RequirementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinalEvaluationTest extends TestCase
{
    use RefreshDatabase;

    private User $lwd;

    private User $lwrd;

    private User $bfmd;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(RequirementSeeder::class);

        $district = WaterDistrict::factory()->create(['name' => 'Hillside Water District', 'category' => 'C']);
        $this->lwd = User::factory()->create(['role' => Role::Lwd, 'water_district_id' => $district->id]);
        $this->lwrd = User::factory()->create(['role' => Role::Lwrd]);
        $this->bfmd = User::factory()->create(['role' => Role::Bfmd]);
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
        return UploadedFile::fake()->create($name, 30, 'application/pdf');
    }

    /**
     * One PDF per required post-evaluation item.
     *
     * @return array<int, list<UploadedFile>>
     */
    private function complianceFiles(Project $project): array
    {
        return $project->complianceRequirements()->reject->is_optional
            ->mapWithKeys(fn (Requirement $r) => [$r->id => [$this->pdf("post-{$r->number}.pdf")]])
            ->all();
    }

    private function postItemId(Project $project, int $number): int
    {
        return Requirement::checklist($project->type, RequirementStage::PostEvaluation)->where('number', $number)->value('id');
    }

    public function test_regular_flow_has_fifteen_steps_with_a_separate_mfta_step(): void
    {
        $keys = array_column($this->projectAt(9)->steps(), 'key');

        $this->assertCount(15, $keys);
        $this->assertSame(['financial_evaluation', 'issue_caf', 'complete_evaluation', 'final_decision'], array_slice($keys, 8, 4));
    }

    public function test_full_final_evaluation_with_a_compliance_return(): void
    {
        $project = $this->projectAt(9);

        // LWRD sends the compliance letter.
        $this->actingAs($this->lwrd)->get(route('office.projects.show', $project))->assertSee('Send Compliance Letter');
        $this->actingAs($this->lwrd)->post(route('office.projects.compliance.request', $project), [
            'remarks' => 'Please send the 5 post-evaluation documents.',
            'letter' => $this->pdf('compliance-letter.pdf'),
        ])->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame(ProjectStatus::ForCompliance, $project->status);
        $this->assertSame(Role::Lwd, $project->current_role);
        $this->assertSame(9, $project->current_step);

        // The LWD sees the request, the letter and the upload screen.
        $this->actingAs($this->lwd)->get('/dashboard')->assertSee('Submit Documents');
        $this->actingAs($this->lwd)->get(route('projects.compliance', $project))
            ->assertOk()
            ->assertSee('Please send the 5 post-evaluation documents.')
            ->assertSee('compliance-letter.pdf')
            ->assertSee('Board Resolution Accepting the Terms and Conditions.')
            ->assertSee('We concur with the cash flow projections');
        $this->actingAs($this->lwd)->get(route('documents.show', $project->documents()->sole()))->assertOk();

        // Sending without the CFP concurrence or files is refused.
        $this->actingAs($this->lwd)->put(route('projects.compliance.update', $project), ['action' => 'submit'])
            ->assertSessionHasErrors('cfp_concurrence');
        $this->actingAs($this->lwd)->put(route('projects.compliance.update', $project), ['action' => 'submit', 'cfp_concurrence' => '1'])
            ->assertSessionHasErrors('documents.'.$this->postItemId($project, 1));

        // A complete submission goes back to LWRD.
        $this->actingAs($this->lwd)->put(route('projects.compliance.update', $project), [
            'action' => 'submit', 'cfp_concurrence' => '1', 'documents' => $this->complianceFiles($project),
        ])->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame(ProjectStatus::UnderEvaluation, $project->status);
        $this->assertSame(Role::Lwrd, $project->current_role);
        $this->assertNotNull($project->cfp_concurred_at);
        $this->assertTrue($project->complianceReceived());

        // LWRD returns item 4 (the GM's ID).
        $item4 = $this->postItemId($project, 4);
        $this->actingAs($this->lwrd)->get(route('office.projects.show', $project))
            ->assertSee('Post-Evaluation Documents')->assertSee('Accept – Request CAF')->assertSee('Flag for return');
        $this->actingAs($this->lwrd)->post(route('office.projects.compliance.return', $project), [
            'items' => [$item4 => ['flag' => '1', 'remarks' => 'The ID has expired.']],
        ])->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame(ProjectStatus::ForCompliance, $project->status);
        $this->actingAs($this->lwd)->get(route('projects.compliance', $project))->assertSee('The ID has expired.');

        // The LWD must upload a new ID, then resends.
        $this->actingAs($this->lwd)->put(route('projects.compliance.update', $project), ['action' => 'submit', 'cfp_concurrence' => '1'])
            ->assertSessionHasErrors("documents.{$item4}");
        $this->actingAs($this->lwd)->put(route('projects.compliance.update', $project), [
            'action' => 'submit', 'cfp_concurrence' => '1', 'documents' => [$item4 => [$this->pdf('new-id.pdf')]],
        ])->assertSessionHasNoErrors();

        // LWRD accepts and requests the CAF.
        $this->actingAs($this->lwrd)->post(route('office.projects.compliance.accept', $project))->assertSessionHasNoErrors();
        $project->refresh();
        $this->assertSame('issue_caf', $project->currentStep()['key']);
        $this->assertSame(Role::Bfmd, $project->current_role);

        // BFMD issues the CAF, which the LWD can see under LWUA Documents.
        $this->actingAs($this->bfmd)->get(route('office.projects.show', $project))->assertSee('Issue CAF');
        $this->actingAs($this->bfmd)->post(route('office.projects.caf', $project), ['caf' => $this->pdf('caf.pdf')])->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame('complete_evaluation', $project->currentStep()['key']);
        $this->assertSame(Role::Lwrd, $project->current_role);
        $caf = $project->documents()->where('original_name', 'caf.pdf')->sole();
        $this->assertSame('Certificate of Availability of Funds', $caf->requirement->description);
        $this->assertFalse($caf->isInternal());

        // LWRD prepares the MFTA and endorses to the Administrator.
        $this->actingAs($this->lwrd)->post(route('office.projects.endorse-to-administrator', $project), [
            'recommendation' => 'approve',
            'memorandum' => $this->pdf('memo.pdf'),
            'mfta' => $this->pdf('mfta.pdf'),
            'remarks' => 'All requirements complete.',
        ])->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame('final_decision', $project->currentStep()['key']);
        $this->assertSame(Role::Administrator, $project->current_role);
        $this->assertStringContainsString('recommending approval', $project->logs()->first()->action);
        $this->assertTrue($project->documents()->where('original_name', 'memo.pdf')->sole()->isInternal());
    }

    public function test_emergency_compliance_has_seven_items_and_no_cfp(): void
    {
        $project = $this->projectAt(4, ProjectType::Emergency, FundingPurpose::OperationsAndMaintenance);
        $this->assertSame('financial_evaluation', $project->currentStep()['key']);

        $this->actingAs($this->lwrd)->post(route('office.projects.compliance.request', $project), ['remarks' => 'Please comply.']);

        $this->actingAs($this->lwd)->get(route('projects.compliance', $project))
            ->assertSee('Board Resolution committing to the conduct of Public Hearing.')
            ->assertDontSee('We concur with the cash flow projections');

        $this->assertCount(7, $project->complianceRequirements());

        $this->actingAs($this->lwd)->put(route('projects.compliance.update', $project), [
            'action' => 'submit', 'documents' => $this->complianceFiles($project),
        ])->assertSessionHasNoErrors();

        $this->assertNull($project->fresh()->cfp_concurred_at);
        $this->assertSame(Role::Lwrd, $project->fresh()->current_role);
    }

    public function test_saving_compliance_documents_keeps_the_status(): void
    {
        $project = $this->projectAt(9);
        $this->actingAs($this->lwrd)->post(route('office.projects.compliance.request', $project), ['remarks' => 'Please comply.']);

        $this->actingAs($this->lwd)->put(route('projects.compliance.update', $project), [
            'action' => 'draft', 'documents' => [$this->postItemId($project, 1) => [$this->pdf('br.pdf')]],
        ])->assertRedirect(route('projects.compliance', $project));

        $this->assertSame(ProjectStatus::ForCompliance, $project->fresh()->status);
        $this->assertCount(1, $project->documents);
    }

    public function test_compliance_page_is_only_for_the_owner_while_for_compliance(): void
    {
        $project = $this->projectAt(9);
        $this->actingAs($this->lwd)->get(route('projects.compliance', $project))->assertForbidden();

        $this->actingAs($this->lwrd)->post(route('office.projects.compliance.request', $project), ['remarks' => 'Please comply.']);

        $other = User::factory()->create(['role' => Role::Lwd, 'water_district_id' => WaterDistrict::factory()->create(['name' => 'Other WD', 'category' => 'A'])->id]);
        $this->actingAs($other)->get(route('projects.compliance', $project))->assertForbidden();
        $this->actingAs($this->lwd)->get(route('projects.compliance', $project))->assertOk();
    }

    public function test_lwrd_cannot_accept_before_the_documents_arrive_or_return_submission_items(): void
    {
        $project = $this->projectAt(9);

        $this->actingAs($this->lwrd)->post(route('office.projects.compliance.accept', $project))->assertForbidden();

        $this->actingAs($this->lwrd)->post(route('office.projects.compliance.request', $project), ['remarks' => 'Please comply.']);
        $this->actingAs($this->lwd)->put(route('projects.compliance.update', $project), [
            'action' => 'submit', 'cfp_concurrence' => '1', 'documents' => $this->complianceFiles($project),
        ]);

        // A pre-evaluation item cannot be flagged in the compliance review.
        $submissionItem = Requirement::checklist($project->type, RequirementStage::PreEvaluation)->where('number', 1)->value('id');
        $this->actingAs($this->lwrd)->post(route('office.projects.compliance.return', $project), [
            'items' => [$submissionItem => ['flag' => '1', 'remarks' => 'x']],
        ])->assertSessionHasErrors('items');
    }

    public function test_caf_and_endorsement_require_their_files(): void
    {
        $atCaf = $this->projectAt(10);
        $this->actingAs($this->bfmd)->post(route('office.projects.caf', $atCaf), [])->assertSessionHasErrors('caf');

        $atMfta = $this->projectAt(11);
        $this->actingAs($this->lwrd)->post(route('office.projects.endorse-to-administrator', $atMfta), ['remarks' => 'x'])
            ->assertSessionHasErrors(['recommendation', 'memorandum']);

        $this->actingAs($this->bfmd)->post(route('office.projects.caf', $atMfta), ['caf' => $this->pdf('caf.pdf')])->assertForbidden();
    }
}

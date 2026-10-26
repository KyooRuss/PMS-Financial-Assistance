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

class AssessmentFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $lwd;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(RequirementSeeder::class);

        $district = WaterDistrict::factory()->create(['name' => 'Hillside Water District', 'category' => 'C']);
        $this->lwd = User::factory()->create(['role' => Role::Lwd, 'water_district_id' => $district->id]);
    }

    private function office(Role $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    /**
     * A project already endorsed by WSUDD and waiting at the given step.
     */
    private function projectAt(int $step, ProjectType $type = ProjectType::Regular50MBelow, ?FundingPurpose $purpose = null): Project
    {
        return Project::factory()->for($this->lwd)->create([
            'type' => $type, 'funding_purpose' => $purpose,
            'status' => ProjectStatus::UnderEvaluation, 'current_step' => $step,
        ]);
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->create($name, 40, 'application/pdf');
    }

    public function test_es_assessment_forwards_to_lwrd(): void
    {
        $project = $this->projectAt(3);
        $es = $this->office(Role::Es);

        $this->actingAs($es)->get(route('office.projects.show', $project))->assertSee('Forward to LWRD');

        $this->actingAs($es)->post(route('office.projects.pow-assessment', $project), [
            'findings' => 'Costing is reasonable.',
            'assessment' => $this->pdf('es-assessment.pdf'),
        ])->assertRedirect(route('dashboard'));

        $project->refresh();
        $this->assertSame(4, $project->current_step);
        $this->assertSame(Role::Lwrd, $project->current_role);

        $log = $project->logs()->first();
        $this->assertSame('ES', $log->office);
        $this->assertSame('Costing is reasonable.', $log->remarks);
        $this->assertTrue($log->documents()->sole()->isInternal());
    }

    public function test_es_findings_are_required(): void
    {
        $project = $this->projectAt(3);

        $this->actingAs($this->office(Role::Es))->post(route('office.projects.pow-assessment', $project), ['findings' => ''])
            ->assertSessionHasErrors('findings');

        $this->assertSame(3, $project->fresh()->current_step);
    }

    public function test_lwrd_rates_viability_and_attaches_loan_records(): void
    {
        $project = $this->projectAt(4);

        $this->actingAs($this->office(Role::Lwrd))->post(route('office.projects.viability-assessment', $project), [
            'viable' => '0',
            'findings' => 'Debt service coverage below 1.2.',
            'loan_records' => $this->pdf('loan-records.pdf'),
        ])->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertFalse($project->is_viable);
        $this->assertSame(5, $project->current_step);          // not viable still goes to CPIC
        $this->assertSame(Role::Cpic, $project->current_role);

        $loanRecords = $project->documents()->sole();
        $this->assertSame('Loan Records', $loanRecords->requirement->description);
        $this->assertFalse($loanRecords->isInternal());
    }

    public function test_cpic_sees_both_assessments(): void
    {
        $project = $this->projectAt(3);
        $this->actingAs($this->office(Role::Es))->post(route('office.projects.pow-assessment', $project), ['findings' => 'ES says the POW is sound.']);
        $this->actingAs($this->office(Role::Lwrd))->post(route('office.projects.viability-assessment', $project), ['viable' => '1', 'findings' => 'LWRD says it is viable.']);

        $this->actingAs($this->office(Role::Cpic))->get(route('office.projects.show', $project))
            ->assertOk()
            ->assertSee('Rapid Assessments')
            ->assertSee('ES says the POW is sound.')
            ->assertSee('LWRD says it is viable.')
            ->assertSee('Financially Viable')
            ->assertSee('Record Decision');
    }

    public function test_cpic_priority_yes_sends_a_regular_request_to_es(): void
    {
        $project = $this->projectAt(5);

        $this->actingAs($this->office(Role::Cpic))->post(route('office.projects.priority', $project), [
            'priority' => '1', 'remarks' => 'Included this quarter.',
        ])->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame(6, $project->current_step);
        $this->assertSame('prepare_pow', $project->currentStep()['key']);
        $this->assertSame(Role::Es, $project->current_role);
    }

    public function test_cpic_priority_no_keeps_a_regular_request_on_file(): void
    {
        $project = $this->projectAt(5);

        $this->actingAs($this->office(Role::Cpic))->post(route('office.projects.priority', $project), [
            'priority' => '0', 'remarks' => 'No budget this year.',
        ]);

        $project->refresh();
        $this->assertSame(ProjectStatus::NotPrioritized, $project->status);
        $this->assertNull($project->current_role);
        $this->assertStringContainsString('Kept on file', $project->logs()->first()->action);

        $this->actingAs($this->lwd)->get(route('dashboard', ['project' => $project->id, 'drawer' => 'progress']))
            ->assertSee('kept on file for future funding')->assertDontSee('No budget this year.');  // offices' remarks stay internal
    }

    public function test_emergency_priority_yes_routes_capex_to_es_and_om_to_lwrd(): void
    {
        $capex = $this->projectAt(3, ProjectType::Emergency, FundingPurpose::Capex);
        $om = $this->projectAt(3, ProjectType::Emergency, FundingPurpose::OperationsAndMaintenance);
        $cpic = $this->office(Role::Cpic);

        foreach ([$capex, $om] as $project) {
            $this->actingAs($cpic)->post(route('office.projects.priority', $project), ['priority' => '1', 'remarks' => 'Urgent.'])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(Role::Es, $capex->fresh()->current_role);
        $this->assertSame('prepare_pow', $capex->fresh()->currentStep()['key']);
        $this->assertSame(Role::Lwrd, $om->fresh()->current_role);
        $this->assertSame('financial_evaluation', $om->fresh()->currentStep()['key']);
    }

    public function test_emergency_priority_no_is_returned_to_wsudd(): void
    {
        $project = $this->projectAt(3, ProjectType::Emergency, FundingPurpose::Capex);

        $this->actingAs($this->office(Role::Cpic))->post(route('office.projects.priority', $project), ['priority' => '0', 'remarks' => 'Not urgent.']);

        $this->assertSame(ProjectStatus::NotPrioritized, $project->fresh()->status);
        $this->assertStringContainsString('Returned to WSUDD', $project->logs()->first()->action);
    }

    public function test_offices_cannot_act_out_of_turn_or_on_the_wrong_step(): void
    {
        $atEs = $this->projectAt(3);
        $atEsLater = $this->projectAt(6); // ES again, but "prepare LWUA-POW"

        $this->actingAs($this->office(Role::Lwrd))->post(route('office.projects.viability-assessment', $atEs), ['viable' => '1', 'findings' => 'x'])
            ->assertForbidden();
        $this->actingAs($this->office(Role::Es))->post(route('office.projects.pow-assessment', $atEsLater), ['findings' => 'x'])
            ->assertForbidden();
        $this->actingAs($this->office(Role::Cpic))->post(route('office.projects.priority', $atEs), ['priority' => '1', 'remarks' => 'x'])
            ->assertForbidden();
    }

    public function test_internal_attachments_are_hidden_from_the_water_district(): void
    {
        $project = $this->projectAt(3);
        $this->actingAs($this->office(Role::Es))->post(route('office.projects.pow-assessment', $project), [
            'findings' => 'Fine.', 'assessment' => $this->pdf('internal-es-notes.pdf'),
        ]);
        $document = $project->documents()->sole();

        $this->actingAs($this->lwd)->get(route('dashboard', ['project' => $project->id, 'drawer' => 'progress']))
            ->assertDontSee('internal-es-notes.pdf');
        $this->actingAs($this->lwd)->get(route('documents.show', $document))->assertForbidden();

        $this->actingAs($this->office(Role::Cpic))->get(route('documents.show', $document))->assertOk();
    }
}

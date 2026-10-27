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
            ->assertSee('Approve')->assertSee('Reject')->assertDontSee('DISAPPROVED')
            ->assertSee('x-show="flagged" x-cloak', false)  // "Return to Water District" shows only once an item is flagged
            ->assertSee('Return to Water District')
            ->assertSee('Assign a New Loan Amount')
            ->assertSee('This amount replaces the requested one.');
    }

    public function test_the_amount_wsudd_assigns_replaces_the_requested_amount(): void
    {
        $project = $this->submittedProject();

        $this->actingAs($this->wsudd)->post(route('office.projects.endorse', $project), ['amount' => 18_500_000])
            ->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame('18500000.00', $project->amount);
        $this->assertSame(Role::Es, $project->current_role);
        $this->assertStringContainsString('Assigned a new loan amount of ₱18,500,000.00 (requested: ₱20,000,000.00)', $project->logs()->first()->action);
    }

    public function test_keeping_the_requested_amount_adds_no_note_to_the_timeline(): void
    {
        $project = $this->submittedProject();

        $this->actingAs($this->wsudd)->post(route('office.projects.endorse', $project), ['amount' => 20_000_000]);

        $this->assertSame('20000000.00', $project->fresh()->amount);
        $this->assertStringNotContainsString('Assigned a new loan amount', $project->logs()->first()->action);
    }

    public function test_assigning_above_the_threshold_makes_the_request_above_threshold(): void
    {
        $project = $this->submittedProject();
        $documents = $project->documents()->count();

        $this->actingAs($this->wsudd)->post(route('office.projects.endorse', $project), ['amount' => 60_000_000])
            ->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame(ProjectType::RegularAbove50M, $project->type);
        $this->assertSame('60000000.00', $project->amount);
        $this->assertSame(Role::Es, $project->current_role);
        $this->assertStringContainsString('Changed the project type from Within Threshold to Above Threshold', $project->logs()->first()->action);

        // Every uploaded file now belongs to the Above Threshold checklist, so nothing shows as missing.
        $this->assertSame($documents, $project->documents()->whereIn('requirement_id', Requirement::where('project_type', ProjectType::RegularAbove50M)->pluck('id'))->count());
        $this->assertSame(0, $project->submissionRequirements()->reject->is_optional
            ->reject(fn ($requirement) => $project->documents()->where('requirement_id', $requirement->id)->exists())->count());
    }

    public function test_assigning_within_the_threshold_makes_the_request_within_threshold(): void
    {
        $project = $this->submittedProject();
        $project->update(['type' => ProjectType::RegularAbove50M]);
        $project->documents()->delete();
        foreach ($project->submissionRequirements()->reject->is_optional as $requirement) {
            $this->upload($project, $requirement->number);
        }

        $this->actingAs($this->wsudd)->post(route('office.projects.endorse', $project), ['amount' => 50_000_000]);

        $this->assertSame(ProjectType::Regular50MBelow, $project->fresh()->type);
    }

    public function test_an_emergency_loan_keeps_its_type_at_any_amount(): void
    {
        $project = $this->submittedProject(ProjectType::Emergency, FundingPurpose::Capex);

        $this->actingAs($this->wsudd)->post(route('office.projects.endorse', $project), ['amount' => 75_000_000])
            ->assertSessionHasNoErrors();

        $this->assertSame(ProjectType::Emergency, $project->fresh()->type);
    }

    public function test_endorsing_needs_an_amount(): void
    {
        $project = $this->submittedProject();

        $this->actingAs($this->wsudd)->post(route('office.projects.endorse', $project), ['amount' => ''])
            ->assertSessionHasErrors(['amount' => 'Assign the loan amount for this request.']);

        $this->actingAs($this->wsudd)->post(route('office.projects.endorse', $project), ['amount' => 0])
            ->assertSessionHasErrors(['amount' => 'The loan amount must be more than zero.']);

        $project->refresh();
        $this->assertSame(ProjectStatus::Submitted, $project->status);
        $this->assertSame('20000000.00', $project->amount);
    }

    public function test_other_offices_can_view_but_not_act(): void
    {
        $project = $this->submittedProject();
        $es = User::factory()->create(['role' => Role::Es]);

        // The Action box is only shown to the office whose turn it is.
        $this->actingAs($es)->get(route('office.projects.show', $project))
            ->assertOk()
            ->assertDontSee('Waiting on Water and Sanitation Utilities Development Department')
            ->assertDontSee('Return to Water District')
            ->assertDontSee('Flag for return');

        $this->actingAs($es)->post(route('office.projects.endorse', $project))->assertForbidden();
        $this->actingAs($this->lwd)->get(route('office.projects.show', $project))->assertForbidden();
    }

    public function test_endorse_moves_a_regular_request_to_es(): void
    {
        $project = $this->submittedProject();

        $this->actingAs($this->wsudd)->post(route('office.projects.endorse', $project), ['amount' => 20_000_000, 'remarks' => 'All complete.'])
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

        $this->actingAs($this->wsudd)->post(route('office.projects.endorse', $project), ['amount' => 20_000_000]);

        $project->refresh();
        $this->assertSame(3, $project->current_step);
        $this->assertSame(Role::Cpic, $project->current_role);
    }

    public function test_cannot_endorse_with_missing_required_items(): void
    {
        $project = $this->submittedProject();
        $project->documents()->where('requirement_id', $this->requirementId($project, 16))->delete();

        $this->actingAs($this->wsudd)->post(route('office.projects.endorse', $project), ['amount' => 20_000_000])
            ->assertSessionHas('error');

        $this->assertSame(ProjectStatus::Submitted, $project->fresh()->status);
    }

    public function test_wsudd_can_reject_a_request_which_ends_it(): void
    {
        $project = $this->submittedProject();

        $this->actingAs($this->wsudd)->post(route('office.projects.disapprove-request', $project), ['remarks' => 'Not eligible for financial assistance.'])
            ->assertRedirect(route('dashboard'));

        $project->refresh();
        $this->assertSame(ProjectStatus::Disapproved, $project->status);
        $this->assertNull($project->current_role);  // nobody acts on it any more (no LWRD letter either)
        $this->assertSame(2, $project->current_step);
        $this->assertStringContainsString('Rejected the request', $project->logs()->first()->action);

        $this->actingAs($this->wsudd)->get('/dashboard')->assertViewHas('projects', fn ($projects) => $projects->isEmpty());  // no longer in WSUDD's queue
        $this->actingAs($this->wsudd)->get(route('office.projects.show', $project))->assertDontSee(route('office.projects.disapprove-request', $project));
        $this->actingAs($this->lwd)->get(route('dashboard', ['project' => $project->id, 'drawer' => 'progress']))
            ->assertSee('Disapproved')->assertSee('WSUDD rejected this request. The request is closed.')
            ->assertSee('Not eligible for financial assistance.');  // the reason is in the water district's timeline
    }

    public function test_the_water_district_sees_the_rejection_reason_but_not_other_office_remarks(): void
    {
        $project = $this->submittedProject();
        $item16 = $this->requirementId($project, 16);

        // An earlier return with a general remark, then a resubmission, then the rejection.
        $this->actingAs($this->wsudd)->post(route('office.projects.return', $project), [
            'remarks' => 'Please check item 16.',
            'items' => [$item16 => ['flag' => '1', 'remarks' => 'Certification is not signed.']],
        ]);
        $this->actingAs($this->lwd)->put(route('projects.update', $project), [
            'action' => 'submit', 'amount' => 20_000_000,
            'documents' => [$item16 => [UploadedFile::fake()->create('signed-certification.pdf', 30, 'application/pdf')]],
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->wsudd)->post(route('office.projects.disapprove-request', $project), ['remarks' => 'The water source is not sustainable.']);

        $this->actingAs($this->lwd)->get(route('dashboard', ['project' => $project->id, 'drawer' => 'progress']))
            ->assertSeeInOrder(['Rejected the request', 'bg-red-50', 'The water source is not sustainable.', 'bg-amber-50', 'Certification is not signed.'])  // the rejection reason in red, other remarks amber
            ->assertSee('Certification is not signed.')       // flagged-item remark, as before
            ->assertDontSee('Please check item 16.');          // WSUDD's general remark on the return stays hidden
    }

    public function test_rejecting_needs_a_reason(): void
    {
        $project = $this->submittedProject();

        $this->actingAs($this->wsudd)->post(route('office.projects.disapprove-request', $project), ['remarks' => ''])
            ->assertSessionHasErrors(['remarks' => 'Give the reason for rejecting the request.']);

        $this->assertSame(ProjectStatus::Submitted, $project->fresh()->status);
    }

    public function test_only_wsudd_can_reject_and_only_at_the_completeness_check(): void
    {
        $project = $this->submittedProject();

        foreach ([Role::Es, Role::Lwrd, Role::Administrator, Role::SystemAdmin] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->post(route('office.projects.disapprove-request', $project), ['remarks' => 'No.'])
                ->assertForbidden();
        }
        $this->actingAs($this->lwd)->post(route('office.projects.disapprove-request', $project), ['remarks' => 'No.'])->assertForbidden();

        // Once WSUDD has approved it, the request can no longer be rejected here.
        $this->actingAs($this->wsudd)->post(route('office.projects.endorse', $project), ['amount' => 20_000_000]);
        $this->actingAs($this->wsudd)->post(route('office.projects.disapprove-request', $project), ['remarks' => 'No.'])->assertForbidden();

        $this->assertSame(ProjectStatus::UnderEvaluation, $project->fresh()->status);
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

        // The LWD sees the return on the dashboard, the drawer and the form. In the timeline it reads the
        // remarks on the flagged items only, not WSUDD's general remarks.
        $this->actingAs($this->lwd)->get('/dashboard')->assertSee('Resubmit')->assertSee(route('projects.edit', $project));
        $this->actingAs($this->lwd)->get(route('dashboard', ['project' => $project->id, 'drawer' => 'progress']))
            ->assertSee('Item 16:')->assertSee('Certification is not signed.')->assertDontSee('Please check item 16.');
        $this->actingAs($this->wsudd)->get(route('office.projects.show', $project))
            ->assertSee('Certification is not signed.');
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
        $this->actingAs($this->wsudd)->post(route('office.projects.endorse', $project), ['amount' => 20_000_000]);
        $this->assertSame(ProjectStatus::UnderEvaluation, $project->fresh()->status);
    }

    public function test_handled_tab_lists_projects_the_office_acted_on(): void
    {
        $project = $this->submittedProject();
        $this->actingAs($this->wsudd)->post(route('office.projects.endorse', $project), ['amount' => 20_000_000]);

        $this->actingAs($this->wsudd)->get(route('dashboard', ['tab' => 'handled']))
            ->assertOk()->assertSee($project->reference)->assertSee('Under Evaluation');

        $this->actingAs($this->wsudd)->get('/dashboard')->assertSee('Nothing is waiting for your office right now.');
    }
}

<?php

namespace Database\Seeders;

use App\Enums\FundingPurpose;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\RequirementStage;
use App\Enums\Role;
use App\Models\Project;
use App\Models\ProjectLog;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Local sample data so every dashboard, drawer and office queue has something in it. Not for production.
 */
class SampleProjectSeeder extends Seeder
{
    /** Office shown on the stepper => the seeded account that acts for it. */
    private const OFFICE_ROLES = [
        'LWD' => null, // the project's own water district user
        'WSUDD' => Role::Wsudd,
        'WSUDD/UDEV' => Role::Wsudd,
        'ES' => Role::Es,
        'LWRD' => Role::Lwrd,
        'CPIC' => Role::Cpic,
        'Administrator' => Role::Administrator,
        'BFMD/FS' => Role::Bfmd,
        'LPMD/FS' => Role::Lpmd,
        'Legal' => Role::Legal,
    ];

    /** Sample findings recorded on finished steps, keyed by step key. */
    private const STEP_REMARKS = [
        'pow_assessment' => 'Components and costing of the WD-POW are reasonable. Pipe sizes should be checked against the projected demand for 2030.',
        'viability_assessment' => 'Current ratio 1.8; debt service coverage 1.4 after the new loan. Existing LWUA loan is current. Water rates adequate.',
        'prioritization' => 'Included in the priority list for this quarter.',
        'prepare_pow' => 'LWUA-POW follows the WD-POW with an added 10% contingency for pipe materials.',
        'complete_evaluation' => 'All requirements complete. Recommending approval at 7% interest over 20 years.',
        'final_decision' => 'Approved as recommended.',
        'loan_setup' => 'Documents complete. Loan account to be opened on signing of the FAC.',
    ];

    /** Remarks for the step each sample stopped at, keyed by status. */
    private const STOP_REMARKS = [
        'returned' => ['Returned the request to the water district: 1 item(s) to fix', 'Please check item 16 and resubmit.'],
        'not_prioritized' => ['Did not prioritize the request. Kept on file for future funding', 'Not included in this year\'s priority list.'],
        'disapproved' => ['Disapproved the financial assistance', 'Debt service coverage is below 1.0 even with the proposed rate increase.'],
    ];

    /** The document an office produces on a step: [file name, LWUA checklist description or null, internal]. */
    private const STEP_FILES = [
        'prepare_pow' => ['LWUA-POW.pdf', null, true],
        'forward_pow' => ['Approved POW with Project Profile.pdf', 'Approved P%', false],
        'issue_caf' => ['Certificate of Availability of Funds.pdf', 'Certificate of Availability of Funds', false],
        'complete_evaluation' => ['Memorandum for the Administrator.pdf', null, true],
        'notify' => ['Approval letter.pdf', null, false],
        'contract' => ['Financial Assistance Contract.pdf', null, false],
    ];

    public function run(): void
    {
        $categoryA = User::where('email', 'lwd.a@pms.test')->firstOrFail();
        $categoryC = User::where('email', 'lwd.c@pms.test')->firstOrFail();

        $samples = [
            // lwd.a – Category A (regular loans only)
            [$categoryA, ['type' => ProjectType::RegularAbove50M, 'title' => 'Bulk Water Supply Expansion', 'amount' => 120_000_000, 'status' => ProjectStatus::Draft, 'current_step' => 1, 'submitted_at' => null]],
            [$categoryA, ['type' => ProjectType::Regular50MBelow, 'title' => 'Pipeline Rehabilitation – Poblacion', 'amount' => 35_000_000, 'status' => ProjectStatus::UnderEvaluation, 'current_step' => 5, 'is_viable' => true, 'submitted_at' => '2026-09-01 09:00']],
            [$categoryA, ['type' => ProjectType::RegularAbove50M, 'title' => 'Pipe Network Expansion – Zone 3', 'amount' => 75_000_000, 'status' => ProjectStatus::UnderEvaluation, 'current_step' => 7, 'is_viable' => true, 'submitted_at' => '2026-08-03 09:00']],
            [$categoryA, ['type' => ProjectType::Regular50MBelow, 'title' => 'Water Meter Replacement Program', 'amount' => 12_000_000, 'status' => ProjectStatus::Submitted, 'current_step' => 2, 'submitted_at' => '2026-09-28 10:30']],
            [$categoryA, ['type' => ProjectType::Regular50MBelow, 'title' => 'Booster Pump Station – Brgy. Malinis', 'amount' => 28_000_000, 'status' => ProjectStatus::UnderEvaluation, 'current_step' => 9, 'is_viable' => true, 'compliance_submitted_at' => '2026-09-25 14:00', 'submitted_at' => '2026-06-02 09:00']],
            [$categoryA, ['type' => ProjectType::RegularAbove50M, 'title' => 'Treatment Plant Upgrade', 'amount' => 95_000_000, 'status' => ProjectStatus::UnderEvaluation, 'current_step' => 11, 'is_viable' => true, 'submitted_at' => '2026-04-14 09:00']],
            [$categoryA, ['type' => ProjectType::RegularAbove50M, 'title' => 'Elevated Steel Tank – 500 m³', 'amount' => 62_000_000, 'status' => ProjectStatus::Approved, 'current_step' => 13, 'is_viable' => true, 'submitted_at' => '2026-03-02 09:00']],
            [$categoryA, ['type' => ProjectType::Regular50MBelow, 'title' => 'Chlorination Facilities', 'amount' => 8_500_000, 'status' => ProjectStatus::Approved, 'current_step' => 15, 'is_viable' => true, 'submitted_at' => '2026-01-12 09:00']],
            [$categoryA, ['type' => ProjectType::Regular50MBelow, 'title' => 'Office Building and Warehouse', 'amount' => 15_000_000, 'status' => ProjectStatus::Completed, 'current_step' => 15, 'is_viable' => true, 'submitted_at' => '2025-10-06 09:00']],

            // lwd.c – Category C (can also request emergency loans)
            [$categoryC, ['type' => ProjectType::Emergency, 'funding_purpose' => FundingPurpose::Capex, 'title' => 'Typhoon Damage – Intake Rehabilitation', 'amount' => 18_000_000, 'status' => ProjectStatus::Completed, 'current_step' => 13, 'submitted_at' => '2026-02-01 09:00']],
            [$categoryC, ['type' => ProjectType::Regular50MBelow, 'title' => 'New Production Well', 'amount' => 22_500_000, 'status' => ProjectStatus::Returned, 'current_step' => 2, 'submitted_at' => '2026-08-15 09:00']],
            [$categoryC, ['type' => ProjectType::Emergency, 'funding_purpose' => FundingPurpose::OperationsAndMaintenance, 'title' => 'Drought Response – Operating Costs', 'amount' => 6_000_000, 'status' => ProjectStatus::ForCompliance, 'current_step' => 4, 'submitted_at' => '2026-07-20 09:00']],
            [$categoryC, ['type' => ProjectType::Regular50MBelow, 'title' => 'Reservoir Construction – Brgy. San Isidro', 'amount' => 48_000_000, 'status' => ProjectStatus::NotPrioritized, 'current_step' => 5, 'is_viable' => false, 'submitted_at' => '2026-05-10 09:00']],
            [$categoryC, ['type' => ProjectType::Regular50MBelow, 'title' => 'Septage Management Facility', 'amount' => 40_000_000, 'status' => ProjectStatus::UnderEvaluation, 'current_step' => 10, 'is_viable' => true, 'submitted_at' => '2026-05-20 09:00']],
            [$categoryC, ['type' => ProjectType::Regular50MBelow, 'title' => 'Distribution Line Extension – Sitio Proper', 'amount' => 19_000_000, 'status' => ProjectStatus::UnderEvaluation, 'current_step' => 12, 'is_viable' => true, 'submitted_at' => '2026-03-16 09:00']],
            [$categoryC, ['type' => ProjectType::Regular50MBelow, 'title' => 'Spring Box Development', 'amount' => 6_500_000, 'status' => ProjectStatus::Approved, 'current_step' => 14, 'is_viable' => true, 'submitted_at' => '2026-02-23 09:00']],
            [$categoryC, ['type' => ProjectType::Regular50MBelow, 'title' => 'Transmission Line Replacement', 'amount' => 45_000_000, 'status' => ProjectStatus::Disapproved, 'current_step' => 12, 'is_viable' => false, 'submitted_at' => '2026-03-09 09:00']],
        ];

        foreach ($samples as [$user, $attributes]) {
            $project = Project::firstOrCreate(
                ['water_district_id' => $user->water_district_id, 'title' => $attributes['title']],
                [...$attributes, 'user_id' => $user->id, 'description' => 'Sample project for testing the dashboard.'],
            );

            if (! $project->wasRecentlyCreated) {
                continue;
            }

            // DatabaseSeeder runs without model events, so set "whose turn" and the compliance dates by hand.
            $project->forceFill([
                'current_role' => $project->waitingOn(),
                'compliance_requested_at' => $project->status === ProjectStatus::ForCompliance || $this->pastCompliance($project) || $project->compliance_submitted_at
                    ? Carbon::parse($project->submitted_at)->addMonth() : null,
                'cfp_concurred_at' => ! $project->type->isEmergency() && ($this->pastCompliance($project) || $project->compliance_submitted_at)
                    ? Carbon::parse($project->submitted_at)->addMonths(2) : null,
            ])->save();

            if ($project->status !== ProjectStatus::Draft) {
                $this->attachSampleFiles($project, $user);
                $this->writeTimeline($project, $user);
            }
        }
    }

    /**
     * Whether LWRD already accepted the post-evaluation documents (the project is past the final evaluation step).
     */
    private function pastCompliance(Project $project): bool
    {
        $finalEvaluation = collect($project->steps())->search(fn (array $step) => $step['key'] === 'financial_evaluation') + 1;

        return $project->status === ProjectStatus::Completed || $project->current_step > $finalEvaluation;
    }

    /**
     * A placeholder PDF for every required item. The returned sample is missing item 16 on purpose.
     */
    private function attachSampleFiles(Project $project, User $user): void
    {
        $requirements = $project->submissionRequirements();

        if ($this->pastCompliance($project) || $project->compliance_submitted_at) {
            $requirements = $requirements->concat($project->complianceRequirements());
        }

        foreach ($requirements as $requirement) {
            $isSubmission = $requirement->stage === RequirementStage::PreEvaluation;

            if ($requirement->is_optional || ($project->status === ProjectStatus::Returned && $isSubmission && $requirement->number === 16)) {
                continue;
            }

            $prefix = $isSubmission ? 'Item' : 'Post-evaluation item';
            $path = "projects/{$project->id}/sample-{$requirement->stage->value}-{$requirement->number}.pdf";
            Storage::disk('local')->put($path, $this->placeholderPdf("{$project->reference} - {$prefix} {$requirement->number}"));

            $project->documents()->create([
                'requirement_id' => $requirement->id,
                'uploaded_by' => $user->id,
                'original_name' => "{$prefix} {$requirement->number} - ".str($requirement->description)->limit(40, '').'.pdf',
                'path' => $path,
                'size' => Storage::disk('local')->size($path),
            ]);
        }
    }

    /**
     * One log per finished step, plus the reason the sample stopped where it is.
     */
    private function writeTimeline(Project $project, User $lwdUser): void
    {
        $steps = $project->steps();
        $finished = $project->status === ProjectStatus::Completed ? count($steps) : $project->current_step - 1;
        $at = Carbon::parse($project->submitted_at);

        foreach (array_slice($steps, 0, $finished) as $index => $step) {
            if ($step['key'] === 'financial_evaluation') {
                $at = $this->complianceLogs($project, $lwdUser, $index + 1, $at, accepted: true);

                continue;
            }

            // The first entry is the submission itself; each later one comes after that office's usual delay.
            $at = $index === 0 ? $at : $this->after($at, $step['unit'], $project);
            $log = $this->addLog($project, $lwdUser, $step['unit'], $step['action'], self::STEP_REMARKS[$step['key']] ?? null, $index + 1, $at);
            $this->attachStepFile($project, $log, $step['key']);
        }

        if ($project->isAt('financial_evaluation') || $project->status === ProjectStatus::ForCompliance) {
            $this->complianceLogs($project, $lwdUser, $project->current_step, $at, accepted: false);

            return;
        }

        if ($stop = self::STOP_REMARKS[$project->status->value] ?? null) {
            $step = $steps[$project->current_step - 1];
            $at = $this->after($at, $step['unit'], $project);
            $log = $this->addLog($project, $lwdUser, $step['unit'], $stop[0], $stop[1], $project->current_step, $at);

            if ($project->status === ProjectStatus::Returned) {
                $project->returnedItems()->create([
                    'requirement_id' => $project->submissionRequirements()->firstWhere('number', 16)->id,
                    'project_log_id' => $log->id,
                    'remarks' => 'The certification of water source is missing. Upload a signed certification on quantity and water quality.',
                ]);
            }
        }
    }

    /**
     * LWRD's compliance letter, the LWD's answer (if sent) and LWRD's acceptance (if accepted).
     */
    private function complianceLogs(Project $project, User $lwdUser, int $step, Carbon $at, bool $accepted): Carbon
    {
        $at = $this->after($at, 'LWRD', $project);
        $letter = $this->addLog($project, $lwdUser, 'LWRD', 'Sent the compliance letter requesting the post-evaluation documents',
            'Please submit the post-evaluation documents listed in your PMS account.', $step, $at);
        $this->attachFile($project, $letter, 'Compliance letter.pdf', null, internal: false);

        if ($project->status === ProjectStatus::ForCompliance) {
            return $at;
        }

        $at = $this->after($at, 'LWD', $project);
        $this->addLog($project, $lwdUser, 'LWD', $project->type->isEmergency()
            ? 'Submitted the post-evaluation documents'
            : 'Submitted the post-evaluation documents and concurred with the cash flow projections', null, $step, $at);

        if ($accepted) {
            $at = $this->after($at, 'LWRD', $project);
            $this->addLog($project, $lwdUser, 'LWRD', 'Accepted the post-evaluation documents and requested the CAF from BFMD/FS', null, $step, $at);
        }

        return $at;
    }

    /**
     * When the office acts, counting from the previous action: its usual delay in days, varied a little per
     * project so the Processing Time report has realistic spread. Never in the future.
     */
    private function after(Carbon $previous, string $office, Project $project): Carbon
    {
        $usualDays = ['WSUDD' => 2, 'WSUDD/UDEV' => 2, 'ES' => 6, 'LWRD' => 5, 'CPIC' => 12, 'Administrator' => 4,
            'BFMD/FS' => 3, 'LPMD/FS' => 2, 'Legal' => 8, 'LWD' => 9][$office] ?? 3;
        $variation = (($project->id * 7 + strlen($office) * 3) % 5) - 2; // -2 … +2 days

        $next = $previous->copy()->addDays(max(1, $usualDays + $variation))->addHours(($project->id * 5) % 9);

        return $next->min(now()->subHour())->max($previous->copy()->addHour());
    }

    private function attachStepFile(Project $project, ProjectLog $log, string $stepKey): void
    {
        if ($file = self::STEP_FILES[$stepKey] ?? null) {
            $this->attachFile($project, $log, ...$file);
        }
    }

    private function attachFile(Project $project, ProjectLog $log, string $name, ?string $lwuaDocument, bool $internal): void
    {
        $path = "projects/{$project->id}/sample-log-{$log->id}.pdf";
        Storage::disk('local')->put($path, $this->placeholderPdf("{$project->reference} - {$name}"));

        $project->documents()->create([
            'requirement_id' => $lwuaDocument
                ? Requirement::checklist($project->type, RequirementStage::LwuaUnit)->where('description', 'like', $lwuaDocument)->value('id')
                : null,
            'project_log_id' => $log->id,
            'internal' => $internal,
            'uploaded_by' => $log->user_id,
            'original_name' => $name,
            'path' => $path,
            'size' => Storage::disk('local')->size($path),
        ]);
    }

    private function addLog(Project $project, User $lwdUser, string $office, string $action, ?string $remarks, int $step, Carbon $at): ProjectLog
    {
        $role = self::OFFICE_ROLES[$office] ?? null;

        return $project->logs()->create([
            'user_id' => $role ? User::where('role', $role)->value('id') : $lwdUser->id,
            'office' => $office,
            'action' => $action,
            'remarks' => $remarks,
            'step' => $step,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    /**
     * Smallest valid one-page PDF showing a label, so "View" opens something real.
     */
    private function placeholderPdf(string $label): string
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], "Sample document: {$label}");
        $stream = "BT /F1 14 Tf 72 720 Td ({$text}) Tj ET";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }
}

<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\RequirementStage;
use App\Models\Project;
use App\Models\ProjectLog;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Moves a project along its process flow. Every move writes a timeline entry.
 */
class ProjectWorkflow
{
    /**
     * WSUDD found the submission complete and passes it on (regular: to ES and LWRD; emergency: to CPIC).
     */
    public function endorse(Project $project, User $user, ?string $remarks = null): void
    {
        $action = $project->type->isEmergency()
            ? __('Endorsed to CPIC with complete requirements')
            : __('Found the requirements complete. Endorsed items 1–11 to LWRD and items 12–16 with the WD-POW to ES');

        $this->advance($project, $user, $action, $remarks);
    }

    /**
     * Sends the request back to the water district with remarks on specific checklist items.
     *
     * @param  array<int, string>  $items  requirement id => what is wrong with it
     */
    public function returnToWaterDistrict(Project $project, User $user, array $items, ?string $remarks = null): void
    {
        DB::transaction(function () use ($project, $user, $items, $remarks) {
            $log = $project->log($user, __('Returned the request to the water district: :count item(s) to fix', ['count' => count($items)]), $remarks);

            foreach ($items as $requirementId => $itemRemarks) {
                $project->returnedItems()->create([
                    'requirement_id' => $requirementId,
                    'project_log_id' => $log->id,
                    'remarks' => $itemRemarks,
                ]);
            }

            $project->update(['status' => ProjectStatus::Returned]);
        });
    }

    /**
     * The water district sends its request (first time or after a return) to WSUDD.
     */
    public function submit(Project $project, User $user): void
    {
        DB::transaction(function () use ($project, $user) {
            if ($project->status === ProjectStatus::Returned) {
                $project->log($user, __('Resubmitted the request with corrections'), office: 'LWD');
                $project->returnedItems()->open()->update(['resolved_at' => now()]);
            } else {
                $project->log($user, __('Submitted the request and requirements'), office: 'LWD');
            }

            $project->update([
                'status' => ProjectStatus::Submitted,
                'current_step' => 2,
                'submitted_at' => $project->submitted_at ?? now(),
            ]);
        });
    }

    /**
     * ES: rapid assessment of the WD-POW, then on to LWRD.
     */
    public function assessPow(Project $project, User $user, string $findings, ?UploadedFile $assessment = null): void
    {
        $this->advance($project, $user, __('Completed the rapid assessment of the WD-POW and forwarded it to LWRD'), $findings, [$assessment]);
    }

    /**
     * LWRD: rapid assessment of financial viability, with the loan records, then on to CPIC.
     * A "not viable" rating still goes to CPIC, which decides.
     */
    public function assessViability(Project $project, User $user, bool $viable, string $findings, ?UploadedFile $assessment = null, ?UploadedFile $loanRecords = null): void
    {
        $loanRecordsId = $this->lwuaDocumentId($project, 'Loan Records');

        $project->is_viable = $viable;

        $this->advance(
            $project,
            $user,
            $viable ? __('Rated the request Financially Viable and forwarded it to CPIC') : __('Rated the request Financially Not Viable and forwarded it to CPIC'),
            $findings,
            [$assessment, $loanRecords ? [$loanRecords, $loanRecordsId] : null],
        );
    }

    /**
     * CPIC: priority yes moves on (regular: ES prepares the LWUA-POW; emergency: ES for CAPEX, LWRD for O&M).
     * Priority no stops the request (regular: kept on file; emergency: returned to WSUDD to inform the LWD).
     */
    public function prioritize(Project $project, User $user, bool $priority, string $remarks): void
    {
        if ($priority) {
            $this->advance($project, $user, __('Included the request in the priority list'), $remarks);

            return;
        }

        DB::transaction(function () use ($project, $user, $remarks) {
            $project->log($user, $project->type->isEmergency()
                ? __('Did not prioritize the request. Returned to WSUDD to inform the water district')
                : __('Did not prioritize the request. Kept on file for future funding'), $remarks);

            $project->update(['status' => ProjectStatus::NotPrioritized]);
        });
    }

    /**
     * ES: prepares the LWUA-POW (internal) and submits it to the Administrator.
     */
    public function preparePow(Project $project, User $user, UploadedFile $lwuaPow, ?string $remarks = null): void
    {
        $this->advance($project, $user, __('Prepared the LWUA-POW and submitted it to the Administrator for approval'), $remarks, [$lwuaPow]);
    }

    /**
     * Administrator: approves the LWUA-POW; ES then forwards it to LWRD.
     */
    public function approvePow(Project $project, User $user, ?string $remarks = null): void
    {
        $this->advance($project, $user, __('Approved the LWUA-POW'), $remarks);
    }

    /**
     * Administrator: sends the LWUA-POW back to ES (one step back) with what to revise.
     */
    public function returnPowForRevision(Project $project, User $user, string $remarks): void
    {
        DB::transaction(function () use ($project, $user, $remarks) {
            $project->log($user, __('Returned the LWUA-POW to ES for revision'), $remarks);
            $project->current_step--;
            $project->save();
        });
    }

    /**
     * ES: uploads the Approved POW with Project Profile (an annex "Concerned LWUA Units" document) and forwards to LWRD.
     */
    public function forwardApprovedPow(Project $project, User $user, UploadedFile $approvedPow, ?string $remarks = null): void
    {
        $requirementId = Requirement::checklist($project->type, RequirementStage::LwuaUnit)
            ->where('responsible_unit', 'ES')
            ->where('description', 'like', 'Approved P%')
            ->value('id');

        $this->advance($project, $user, __('Forwarded the Approved POW with Project Profile to LWRD'), $remarks, [[$approvedPow, $requirementId]]);
    }

    /**
     * LWRD (final evaluation): sends the compliance letter asking for the post-evaluation documents
     * and, for regular loans, concurrence with the cash flow projections.
     */
    public function requestCompliance(Project $project, User $user, string $remarks, ?UploadedFile $letter = null): void
    {
        DB::transaction(function () use ($project, $user, $remarks, $letter) {
            $log = $project->log($user, __('Sent the compliance letter requesting the post-evaluation documents'), $remarks);

            if ($letter) {
                $project->addDocument($letter, $user, log: $log); // the water district needs to read it
            }

            $project->update([
                'status' => ProjectStatus::ForCompliance,
                'compliance_requested_at' => now(),
                'compliance_submitted_at' => null,
            ]);
        });
    }

    /**
     * The water district sends its post-evaluation documents back to LWRD.
     */
    public function submitCompliance(Project $project, User $user, bool $concursWithCashFlow): void
    {
        DB::transaction(function () use ($project, $user, $concursWithCashFlow) {
            $project->log($user, $concursWithCashFlow
                ? __('Submitted the post-evaluation documents and concurred with the cash flow projections')
                : __('Submitted the post-evaluation documents'), office: 'LWD');
            $project->returnedItems()->open()->update(['resolved_at' => now()]);

            $project->update([
                'status' => ProjectStatus::UnderEvaluation,
                'compliance_submitted_at' => now(),
                'cfp_concurred_at' => $concursWithCashFlow ? now() : $project->cfp_concurred_at,
            ]);
        });
    }

    /**
     * LWRD: sends the post-evaluation documents back with remarks on specific items.
     *
     * @param  array<int, string>  $items  requirement id => what is wrong with it
     */
    public function returnCompliance(Project $project, User $user, array $items, ?string $remarks = null): void
    {
        DB::transaction(function () use ($project, $user, $items, $remarks) {
            $log = $project->log($user, __('Returned the post-evaluation documents: :count item(s) to fix', ['count' => count($items)]), $remarks);

            foreach ($items as $requirementId => $itemRemarks) {
                $project->returnedItems()->create(['requirement_id' => $requirementId, 'project_log_id' => $log->id, 'remarks' => $itemRemarks]);
            }

            $project->update(['status' => ProjectStatus::ForCompliance, 'compliance_submitted_at' => null]);
        });
    }

    /**
     * LWRD: the post-evaluation documents are complete; request the CAF from BFMD/FS.
     */
    public function acceptCompliance(Project $project, User $user, ?string $remarks = null): void
    {
        $this->advance($project, $user, __('Accepted the post-evaluation documents and requested the CAF from BFMD/FS'), $remarks);
    }

    /**
     * BFMD/FS: issues the Certificate of Availability of Funds (an annex "Concerned LWUA Units" document).
     */
    public function issueCaf(Project $project, User $user, UploadedFile $caf, ?string $remarks = null): void
    {
        $this->advance($project, $user, __('Issued the Certificate of Availability of Funds'), $remarks, [[$caf, $this->lwuaDocumentId($project, 'Certificate of Availability of Funds')]]);
    }

    /**
     * LWRD: prepares the MFTA and the Memorandum for the Administrator, and endorses with a recommendation.
     */
    public function endorseToAdministrator(Project $project, User $user, bool $recommendApproval, UploadedFile $memorandum, ?UploadedFile $mfta, string $remarks): void
    {
        $this->advance(
            $project,
            $user,
            $recommendApproval
                ? __('Prepared the MFTA and endorsed the request to the Administrator, recommending approval')
                : __('Prepared the MFTA and endorsed the request to the Administrator, recommending disapproval'),
            $remarks,
            [$memorandum, $mfta], // internal until the Administrator acts
        );
    }

    /**
     * Administrator: approves the loan. An optional signed memorandum becomes the annex
     * "Approved Loan per Memorandum for the Administrator" document.
     */
    public function approveLoan(Project $project, User $user, ?string $remarks = null, ?UploadedFile $signedMemorandum = null): void
    {
        $this->advance(
            $project,
            $user,
            __('Approved the financial assistance'),
            $remarks,
            [$signedMemorandum ? [$signedMemorandum, $this->lwuaDocumentId($project, 'Approved Loan per Memorandum for the Administrator')] : null],
            ProjectStatus::Approved,
        );
    }

    /**
     * Administrator: disapproves the loan. The request waits for LWRD's letter, then closes.
     */
    public function disapproveLoan(Project $project, User $user, string $remarks): void
    {
        DB::transaction(function () use ($project, $user, $remarks) {
            $project->log($user, __('Disapproved the financial assistance'), $remarks);
            $project->update(['status' => ProjectStatus::Disapproved]);
        });
    }

    /**
     * LWRD: informs the water district of the disapproval. This closes the request.
     */
    public function sendDisapprovalLetter(Project $project, User $user, UploadedFile $letter, ?string $remarks = null): void
    {
        DB::transaction(function () use ($project, $user, $letter, $remarks) {
            $log = $project->log($user, __('Sent the letter informing the water district of the disapproval'), $remarks, office: 'LWRD');
            $this->attach($project, $user, $log, [[$letter, null, false]]);
            $project->update(['disapproval_letter_sent_at' => now()]);
        });
    }

    /**
     * LWRD: sends the approval letter to the water district and furnishes the approved MFTA to LPMD.
     */
    public function sendApprovalLetter(Project $project, User $user, UploadedFile $letter, ?UploadedFile $mfta = null, ?string $remarks = null): void
    {
        $this->advance($project, $user, __('Sent the approval letter to the water district and furnished the approved MFTA to LPMD'), $remarks, [
            [$letter, null, false],   // the water district reads it
            $mfta,                    // internal
        ]);
    }

    /**
     * LPMD/FS: secures the required documents and endorses to Legal for the contract.
     */
    public function endorseToLegal(Project $project, User $user, ?string $remarks = null): void
    {
        $this->advance($project, $user, __('Secured the required documents and endorsed to Legal for FAC preparation'), $remarks);
    }

    /**
     * Legal: prepares the Financial Assistance Contract. This completes the request.
     */
    public function completeContract(Project $project, User $user, UploadedFile $contract, ?string $remarks = null): void
    {
        DB::transaction(function () use ($project, $user, $contract, $remarks) {
            $log = $project->log($user, __('Prepared the Financial Assistance Contract'), $remarks);
            $this->attach($project, $user, $log, [[$contract, null, false]]);
            $project->update(['status' => ProjectStatus::Completed]);
        });
    }

    /**
     * @param  list<UploadedFile|array{0: UploadedFile, 1: int|null, 2?: bool}|null>  $files  see advance()
     */
    private function attach(Project $project, User $user, ProjectLog $log, array $files): void
    {
        foreach (array_filter($files) as $file) {
            [$upload, $requirementId, $internal] = is_array($file) ? [$file[0], $file[1] ?? null, $file[2] ?? false] : [$file, null, true];
            $project->addDocument($upload, $user, $requirementId, $log, $internal);
        }
    }

    private function lwuaDocumentId(Project $project, string $description): ?int
    {
        return Requirement::checklist($project->type, RequirementStage::LwuaUnit)->where('description', $description)->value('id');
    }

    /**
     * Logs the action, stores any files with it, and moves the project to the next step.
     *
     * A bare file is an internal office attachment; [file, requirement id] is an annex document the
     * water district can see; [file, requirement id, internal] sets visibility explicitly.
     *
     * @param  list<UploadedFile|array{0: UploadedFile, 1: int|null, 2?: bool}|null>  $files
     * @param  ProjectStatus|null  $status  defaults to Under Evaluation (or stays Approved once approved)
     */
    private function advance(Project $project, User $user, string $action, ?string $remarks = null, array $files = [], ?ProjectStatus $status = null): ProjectLog
    {
        return DB::transaction(function () use ($project, $user, $action, $remarks, $files, $status) {
            $log = $project->log($user, $action, $remarks);
            $this->attach($project, $user, $log, $files);

            $project->status = $status
                ?? ($project->status === ProjectStatus::Approved ? ProjectStatus::Approved : ProjectStatus::UnderEvaluation);
            $project->current_step++;
            $project->save();

            return $log;
        });
    }
}

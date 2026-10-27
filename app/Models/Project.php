<?php

namespace App\Models;

use App\Enums\FundingPurpose;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\RequirementStage;
use App\Enums\Role;
use App\Support\ProcessFlow;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;

#[Fillable([
    'water_district_id', 'user_id', 'type', 'funding_purpose', 'amount',
    'status', 'current_step', 'submitted_at', 'is_viable',
    'compliance_requested_at', 'compliance_submitted_at', 'cfp_concurred_at', 'disapproval_letter_sent_at',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * Same defaults as the table, so a project created in this request already knows its step.
     */
    protected $attributes = [
        'status' => 'draft',
        'current_step' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProjectType::class,
            'funding_purpose' => FundingPurpose::class,
            'status' => ProjectStatus::class,
            'amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'current_role' => Role::class,
            'is_viable' => 'boolean',
            'compliance_requested_at' => 'datetime',
            'compliance_submitted_at' => 'datetime',
            'cfp_concurred_at' => 'datetime',
            'disapproval_letter_sent_at' => 'datetime',
        ];
    }

    /**
     * The Administrator disapproved the request and LWRD still has to send the letter.
     */
    public function awaitsDisapprovalLetter(): bool
    {
        return $this->status === ProjectStatus::Disapproved && $this->disapproval_letter_sent_at === null
            && ! $this->disapprovedByWsudd();
    }

    /**
     * WSUDD disapproved the request at the completeness check. That ends it at once, with no letter from LWRD.
     */
    public function disapprovedByWsudd(): bool
    {
        return $this->status === ProjectStatus::Disapproved && $this->currentStep()['key'] === 'completeness';
    }

    /**
     * LWRD has the post-evaluation documents back from the water district and is reviewing them.
     */
    public function complianceReceived(): bool
    {
        return $this->isAt('financial_evaluation') && $this->compliance_submitted_at !== null;
    }

    /**
     * Post-evaluation checklist (Annex "For Post-Financial Evaluation") the water district uploads on LWRD's request.
     *
     * @return Collection<int, Requirement>
     */
    public function complianceRequirements(): Collection
    {
        return Requirement::checklist($this->type, RequirementStage::PostEvaluation)->get();
    }

    protected static function booted(): void
    {
        // Keep "whose turn is it" in a column so each office's queue is a simple query.
        static::saving(fn (Project $project) => $project->current_role = $project->waitingOn());
    }

    /**
     * The role that has to act next, or null when the flow has ended.
     */
    public function waitingOn(): ?Role
    {
        return match (true) {
            $this->status->needsWaterDistrictAction() => Role::Lwd,
            $this->awaitsDisapprovalLetter() => Role::Lwrd,
            $this->status->isStopped(), $this->status === ProjectStatus::Completed => null,
            default => ProcessFlow::roleFor($this->currentStep()['unit']),
        };
    }

    /**
     * Projects waiting on the given office.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAwaiting(Builder $query, Role $role): void
    {
        $query->where('current_role', $role);
    }

    /**
     * @return BelongsTo<WaterDistrict, $this>
     */
    public function waterDistrict(): BelongsTo
    {
        return $this->belongsTo(WaterDistrict::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ProjectDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }

    /**
     * @return HasMany<ProjectLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ProjectLog::class)->latest()->latest('id');
    }

    /**
     * @return HasMany<ReturnedItem, $this>
     */
    public function returnedItems(): HasMany
    {
        return $this->hasMany(ReturnedItem::class);
    }

    /**
     * Adds an entry to the project's timeline, credited to the office at the current step.
     */
    public function log(User $user, string $action, ?string $remarks = null, ?string $office = null): ProjectLog
    {
        return $this->logs()->create([
            'user_id' => $user->id,
            'office' => $office ?? $this->currentStep()['unit'],
            'action' => $action,
            'remarks' => $remarks,
            'step' => $this->current_step,
        ]);
    }

    /**
     * @return array{key: string, unit: string, action: string}
     */
    public function currentStep(): array
    {
        $steps = $this->steps();

        return $steps[min($this->progressStep(), count($steps)) - 1];
    }

    /**
     * Whether the project is at the given step (by key) and still moving, e.g. isAt('prioritization').
     */
    public function isAt(string $stepKey): bool
    {
        return $this->currentStep()['key'] === $stepKey
            && in_array($this->status, [ProjectStatus::Submitted, ProjectStatus::UnderEvaluation, ProjectStatus::Approved], true);
    }

    /**
     * Timeline entries recorded on a step (by key), newest first.
     *
     * @return \Illuminate\Support\Collection<int, ProjectLog>
     */
    public function logsForStep(string $stepKey): \Illuminate\Support\Collection
    {
        $number = collect($this->steps())->search(fn (array $step) => $step['key'] === $stepKey);

        return $number === false ? collect() : $this->logs->where('step', $number + 1)->values();
    }

    /**
     * Stores an uploaded file on the private disk and records it on the project.
     */
    public function addDocument(UploadedFile $file, User $uploadedBy, ?int $requirementId = null, ?ProjectLog $log = null, bool $internal = false): ProjectDocument
    {
        return $this->documents()->create([
            'requirement_id' => $requirementId,
            'project_log_id' => $log?->id,
            'internal' => $internal,
            'uploaded_by' => $uploadedBy->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $file->store("projects/{$this->id}", 'local'),
            'size' => $file->getSize(),
        ]);
    }

    /**
     * Plain-language explanation of where the request is, for the water district.
     */
    public function statusMessage(): string
    {
        $step = $this->currentStep();

        return match ($this->status) {
            ProjectStatus::Draft => __('Finish uploading your requirements, then submit your request.'),
            ProjectStatus::Submitted => __(':unit is checking that your submission is complete.', ['unit' => $step['unit']]),
            ProjectStatus::Returned => __('Your request was returned because some requirements are incomplete. Check the remarks, fix the items and resubmit.'),
            ProjectStatus::UnderEvaluation, ProjectStatus::Approved => __('Now with :unit – :action.', $step),
            ProjectStatus::NotPrioritized => $this->type->isEmergency()
                ? __('CPIC did not prioritize this request. It was returned to WSUDD, who will inform you.')
                : __('CPIC did not prioritize this request. It is kept on file for future funding.'),
            ProjectStatus::ForCompliance => __('LWRD is waiting for your post-evaluation documents and your concurrence with the cash flow projections.'),
            ProjectStatus::Disapproved => match (true) {
                $this->disapprovedByWsudd() => __('WSUDD disapproved this request. The request is closed.'),
                $this->disapproval_letter_sent_at !== null => __('The Administrator disapproved this request. LWRD\'s letter is in the timeline.'),
                default => __('The Administrator disapproved this request. LWRD will send you a letter.'),
            },
            ProjectStatus::Completed => __('The Financial Assistance Contract has been prepared.'),
        };
    }

    /**
     * Checklist state of one requirement: returned, uploaded, missing, optional or not_applicable.
     *
     * @param  \Illuminate\Support\Collection<int, ProjectDocument>  $files  this project's files for the requirement
     * @param  ReturnedItem|null  $return  an open return flagged on this requirement
     */
    public function requirementState(Requirement $requirement, \Illuminate\Support\Collection $files, ?ReturnedItem $return = null): string
    {
        return match (true) {
            ! $requirement->appliesTo($this->funding_purpose) => 'not_applicable',
            $return !== null => 'returned',
            $files->isNotEmpty() => 'uploaded',
            $requirement->is_optional => 'optional',
            default => 'missing',
        };
    }

    /**
     * Pre-evaluation checklist the water district uploads, minus items for the other funding purpose.
     *
     * @return Collection<int, Requirement>
     */
    public function submissionRequirements(): Collection
    {
        return Requirement::checklist($this->type, RequirementStage::PreEvaluation)
            ->get()
            ->filter(fn (Requirement $requirement) => $requirement->appliesTo($this->funding_purpose))
            ->values();
    }

    /**
     * The water district can change its uploads while drafting, and when a request was returned to it.
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [ProjectStatus::Draft, ProjectStatus::Returned], true);
    }

    /**
     * Display ID shown in the UI, e.g. "P001".
     */
    protected function reference(): Attribute
    {
        return Attribute::get(fn () => 'P'.str_pad((string) $this->id, 3, '0', STR_PAD_LEFT));
    }

    /**
     * @return list<array{unit: string, action: string}>
     */
    public function steps(): array
    {
        return ProcessFlow::steps($this->type, $this->funding_purpose);
    }

    /**
     * The step shown as reached in "Step X of Y"; a completed project has reached the last step.
     */
    public function progressStep(): int
    {
        return $this->status === ProjectStatus::Completed ? count($this->steps()) : $this->current_step;
    }

    /**
     * Stepper state for a 1-based step number: done, current, returned, stopped or upcoming.
     */
    public function stepState(int $step): string
    {
        if ($this->status === ProjectStatus::Completed || $step < $this->current_step) {
            return 'done';
        }

        if ($step > $this->current_step) {
            return 'upcoming';
        }

        return match (true) {
            $this->status->isStopped() => 'stopped',
            $this->status === ProjectStatus::Returned => 'returned',
            default => 'current',
        };
    }
}

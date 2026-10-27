<?php

namespace App\Http\Requests;

use App\Enums\FundingPurpose;
use App\Enums\ProjectType;
use App\Enums\RequirementStage;
use App\Models\Project;
use App\Models\Requirement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Saves the Request a Project form, either as a draft or as a final submission.
 */
class SaveProjectRequest extends FormRequest
{
    public const MAX_FILE_KB = 10240; // 10 MB

    public const THRESHOLD = ProjectType::THRESHOLD;

    public function authorize(): bool
    {
        $project = $this->project();

        return $project
            ? $this->user()->can('update', $project)
            : $this->projectType() !== null && $this->user()->canRequest($this->projectType());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $type = $this->projectType();
        $submitting = $this->isSubmitting();

        $amount = [$submitting ? 'required' : 'nullable', 'numeric', 'max:9999999999999.99'];
        $amount[] = match ($type) {
            ProjectType::RegularAbove50M => 'gt:'.self::THRESHOLD,
            ProjectType::Regular50MBelow => 'lte:'.self::THRESHOLD,
            default => 'gt:0',
        };
        if ($type === ProjectType::Regular50MBelow) {
            $amount[] = 'gt:0';
        }

        return [
            'type' => [$this->project() ? 'prohibited' : 'required', Rule::enum(ProjectType::class)],
            'action' => ['required', Rule::in(['draft', 'submit'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => $amount,
            'funding_purpose' => $type?->isEmergency()
                ? [$submitting ? 'required' : 'nullable', Rule::enum(FundingPurpose::class)]
                : ['prohibited'],
            'documents' => ['array'],
            'documents.*' => ['array'],
            'documents.*.*' => ['file', 'mimes:pdf', 'max:'.self::MAX_FILE_KB],
            'remove_documents' => ['array'],
            'remove_documents.*' => ['integer'],
        ];
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || ! $type = $this->projectType()) {
                return;
            }

            $checklist = Requirement::checklist($type, RequirementStage::PreEvaluation)->get()->keyBy('id');

            foreach (array_keys($this->file('documents', [])) as $requirementId) {
                if (! $checklist->has($requirementId)) {
                    $validator->errors()->add('documents', __('One of the uploads does not belong to this checklist.'));

                    return;
                }
            }

            if (! $this->isSubmitting()) {
                return;
            }

            $purpose = FundingPurpose::tryFrom((string) $this->input('funding_purpose'));
            $kept = $this->project()?->documents()
                ->whereNotIn('id', $this->input('remove_documents', []))
                ->pluck('requirement_id')
                ->countBy() ?? collect();

            foreach ($checklist as $requirement) {
                if ($requirement->is_optional || ! $requirement->appliesTo($purpose)) {
                    continue;
                }

                $hasFile = $kept->get($requirement->id, 0) > 0 || count($this->file("documents.{$requirement->id}", [])) > 0;

                if (! $hasFile) {
                    $validator->errors()->add("documents.{$requirement->id}", __('Please upload this requirement.'));
                }
            }

            // Every item flagged in a return needs a file uploaded after the return.
            foreach ($this->project()?->returnedItems()->open()->get() ?? [] as $returned) {
                $key = "documents.{$returned->requirement_id}";
                $corrected = count($this->file($key, [])) > 0
                    || $this->project()->documents()
                        ->where('requirement_id', $returned->requirement_id)
                        ->whereNotIn('id', $this->input('remove_documents', []))
                        ->where('created_at', '>', $returned->created_at)
                        ->exists();

                if (! $corrected && ! $validator->errors()->has($key)) {
                    $validator->errors()->add($key, __('Upload a corrected file for this returned item.'));
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.gt' => $this->projectType() === ProjectType::RegularAbove50M
                ? __('Above Threshold requests must be more than ₱50,000,000.')
                : __('The amount must be more than zero.'),
            'amount.lte' => __('Within Threshold requests must not exceed ₱50,000,000. Choose "Above Threshold" instead.'),
            'documents.*.*.mimes' => __('Only PDF files are accepted.'),
            'documents.*.*.max' => __('Each file must be 10 MB or smaller.'),
            'funding_purpose.required' => __('Choose whether this emergency loan is for CAPEX or O&M.'),
        ];
    }

    public function isSubmitting(): bool
    {
        return $this->input('action') === 'submit';
    }

    public function project(): ?Project
    {
        return $this->route('project');
    }

    public function projectType(): ?ProjectType
    {
        return $this->project()?->type ?? ProjectType::tryFrom((string) $this->input('type'));
    }
}

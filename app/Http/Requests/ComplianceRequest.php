<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Page 8 – the water district's post-evaluation documents, saved or sent to LWRD.
 *
 * Submit: every non-optional post-evaluation item needs a file, every item LWRD returned needs a new file,
 * and regular loans need concurrence with the cash flow projections.
 */
class ComplianceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('comply', $this->project());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $regular = ! $this->project()->type->isEmergency();

        return [
            'action' => ['required', Rule::in(['draft', 'submit'])],
            'cfp_concurrence' => $regular && $this->isSubmitting() ? ['accepted'] : ['nullable'],
            'documents' => ['array'],
            'documents.*' => ['array'],
            'documents.*.*' => ['file', 'mimes:pdf', 'max:'.SaveProjectRequest::MAX_FILE_KB],
            'remove_documents' => ['array'],
            'remove_documents.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cfp_concurrence.accepted' => __('Confirm that the water district concurs with the cash flow projections.'),
            'documents.*.*.mimes' => __('Only PDF files are accepted.'),
            'documents.*.*.max' => __('Each file must be 10 MB or smaller.'),
        ];
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $project = $this->project();
            $checklist = $project->complianceRequirements()->keyBy('id');

            foreach (array_keys($this->file('documents', [])) as $requirementId) {
                if (! $checklist->has($requirementId)) {
                    $validator->errors()->add('documents', __('One of the uploads does not belong to this checklist.'));

                    return;
                }
            }

            if (! $this->isSubmitting()) {
                return;
            }

            $kept = $project->documents()
                ->whereIn('requirement_id', $checklist->keys())
                ->whereNotIn('id', $this->input('remove_documents', []))
                ->get();

            foreach ($checklist as $requirement) {
                $key = "documents.{$requirement->id}";
                $hasFile = $kept->contains('requirement_id', $requirement->id) || count($this->file($key, [])) > 0;

                if (! $requirement->is_optional && ! $hasFile) {
                    $validator->errors()->add($key, __('Please upload this requirement.'));
                }
            }

            foreach ($project->returnedItems()->open()->whereIn('requirement_id', $checklist->keys())->get() as $returned) {
                $key = "documents.{$returned->requirement_id}";
                $corrected = count($this->file($key, [])) > 0
                    || $kept->contains(fn ($doc) => $doc->requirement_id === $returned->requirement_id && $doc->created_at->gt($returned->created_at));

                if (! $corrected && ! $validator->errors()->has($key)) {
                    $validator->errors()->add($key, __('Upload a corrected file for this returned item.'));
                }
            }
        }];
    }

    public function isSubmitting(): bool
    {
        return $this->input('action') === 'submit';
    }

    public function project(): Project
    {
        return $this->route('project');
    }
}

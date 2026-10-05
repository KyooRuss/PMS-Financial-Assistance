<?php

namespace App\Http\Requests\Office;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Returning documents to the water district: at least one checklist item flagged, each with remarks.
 *
 * Used for WSUDD's completeness check (submission checklist) and LWRD's review of the
 * post-evaluation documents (post-evaluation checklist).
 */
class ReturnProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->project();

        return $this->user()->can('act', $project)
            && ($this->isComplianceReview() ? $project->complianceReceived() : $project->isAt('completeness'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'remarks' => ['nullable', 'string', 'max:2000'],
            'items' => ['array'],
            'items.*.flag' => ['sometimes', 'accepted'],
            'items.*.remarks' => ['required_with:items.*.flag', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['items.*.remarks.required_with' => __('Say what needs to be fixed in this item.')];
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

            if ($this->flaggedItems() === []) {
                $validator->errors()->add('items', __('Flag at least one item that the water district needs to fix.'));

                return;
            }

            $allowed = ($this->isComplianceReview()
                ? $this->project()->complianceRequirements()
                : $this->project()->submissionRequirements())->pluck('id')->all();

            if (array_diff(array_keys($this->flaggedItems()), $allowed) !== []) {
                $validator->errors()->add('items', __('One of the flagged items does not belong to this request.'));
            }
        }];
    }

    /**
     * @return array<int, string> requirement id => remarks
     */
    public function flaggedItems(): array
    {
        return collect($this->input('items', []))
            ->filter(fn ($item) => ! empty($item['flag']))
            ->map(fn ($item) => trim((string) ($item['remarks'] ?? '')))
            ->all();
    }

    private function isComplianceReview(): bool
    {
        return $this->routeIs('office.projects.compliance.return');
    }

    private function project(): Project
    {
        return $this->route('project');
    }
}

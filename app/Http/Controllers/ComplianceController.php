<?php

namespace App\Http\Controllers;

use App\Http\Requests\ComplianceRequest;
use App\Models\Project;
use App\Services\ProjectWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Page 8 – the water district uploads its post-evaluation documents when LWRD asks for them.
 */
class ComplianceController extends Controller
{
    public function __construct(private ProjectWorkflow $workflow) {}

    public function edit(Project $project): View
    {
        Gate::authorize('comply', $project);

        $project->load([
            'documents',
            'returnedItems' => fn ($query) => $query->open()->with('log'),
            'logs' => fn ($query) => $query->with('documents'),
        ]);

        return view('projects.compliance', [
            'project' => $project,
            'requirements' => $project->complianceRequirements(),
            // The latest compliance letter from LWRD, with any attached letter file.
            'letter' => $project->logsForStep('financial_evaluation')->first(),
        ]);
    }

    public function update(ComplianceRequest $request, Project $project): RedirectResponse
    {
        DB::transaction(function () use ($request, $project) {
            $project->documents()
                ->whereIn('requirement_id', $project->complianceRequirements()->pluck('id'))
                ->whereIn('id', $request->input('remove_documents', []))
                ->get()
                ->each->delete();

            foreach ($request->file('documents', []) as $requirementId => $files) {
                foreach ($files as $file) {
                    $project->addDocument($file, $request->user(), $requirementId);
                }
            }
        });

        if (! $request->isSubmitting()) {
            return redirect()->route('projects.compliance', $project)
                ->with('success', __('Saved. Send the documents to LWRD when everything is uploaded.'));
        }

        $this->workflow->submitCompliance($project, $request->user(), $request->boolean('cfp_concurrence'));

        return redirect()->route('dashboard', ['project' => $project->id])
            ->with('success', __(':ref: post-evaluation documents sent to LWRD.', ['ref' => $project->reference]));
    }
}

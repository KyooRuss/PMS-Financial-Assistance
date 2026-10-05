<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\ProjectWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Steps 3–5 of the regular flow (and CPIC's step 3 of the emergency flow):
 * ES and LWRD rapid assessments, then CPIC's priority decision.
 */
class AssessmentController extends Controller
{
    private const PDF = ['nullable', 'file', 'mimes:pdf', 'max:10240'];

    public function __construct(private ProjectWorkflow $workflow) {}

    /**
     * ES – rapid assessment of the WD-POW.
     */
    public function pow(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeStep($project, 'pow_assessment');

        $validated = $request->validate([
            'findings' => ['required', 'string', 'max:3000'],
            'assessment' => self::PDF,
        ]);

        $this->workflow->assessPow($project, $request->user(), $validated['findings'], $request->file('assessment'));

        return $this->done($project);
    }

    /**
     * LWRD – rapid assessment of financial viability.
     */
    public function viability(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeStep($project, 'viability_assessment');

        $validated = $request->validate([
            'viable' => ['required', 'boolean'],
            'findings' => ['required', 'string', 'max:3000'],
            'assessment' => self::PDF,
            'loan_records' => self::PDF,
        ], ['viable.required' => __('Choose Financially Viable or Not Viable.')]);

        $this->workflow->assessViability(
            $project,
            $request->user(),
            (bool) $validated['viable'],
            $validated['findings'],
            $request->file('assessment'),
            $request->file('loan_records'),
        );

        return $this->done($project);
    }

    /**
     * CPIC – funding / prioritization.
     */
    public function priority(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeStep($project, 'prioritization');

        $validated = $request->validate([
            'priority' => ['required', 'boolean'],
            'remarks' => ['required', 'string', 'max:3000'],
        ], ['priority.required' => __('Choose whether the request is a priority.')]);

        $this->workflow->prioritize($project, $request->user(), (bool) $validated['priority'], $validated['remarks']);

        return $this->done($project);
    }

    private function authorizeStep(Project $project, string $stepKey): void
    {
        Gate::authorize('act', $project);
        abort_unless($project->isAt($stepKey), 403, __('This request is not at that step.'));
    }

    private function done(Project $project): RedirectResponse
    {
        $message = $project->waitingOn()
            ? __(':ref is now with :office.', ['ref' => $project->reference, 'office' => $project->waitingOn()->label()])
            : __(':ref: :status.', ['ref' => $project->reference, 'status' => $project->status->label()]);

        return redirect()->route('dashboard')->with('success', $message);
    }
}

<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\ProjectWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The LWUA Program of Work: ES prepares it, the Administrator approves it (or returns it for revision),
 * and ES forwards the approved POW to LWRD. Regular steps 6–8; emergency CAPEX steps 4–6.
 */
class ProgramOfWorkController extends Controller
{
    private const REQUIRED_PDF = ['required', 'file', 'mimes:pdf', 'max:10240'];

    public function __construct(private ProjectWorkflow $workflow) {}

    public function prepare(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeStep($project, 'prepare_pow');

        $validated = $request->validate([
            'lwua_pow' => self::REQUIRED_PDF,
            'remarks' => ['nullable', 'string', 'max:3000'],
        ], ['lwua_pow.required' => __('Upload the LWUA-POW.')]);

        $this->workflow->preparePow($project, $request->user(), $request->file('lwua_pow'), $validated['remarks'] ?? null);

        return $this->done($project);
    }

    public function approve(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeStep($project, 'approve_pow');

        $validated = $request->validate(['remarks' => ['nullable', 'string', 'max:3000']]);

        $this->workflow->approvePow($project, $request->user(), $validated['remarks'] ?? null);

        return $this->done($project);
    }

    public function returnForRevision(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeStep($project, 'approve_pow');

        $validated = $request->validate(
            ['remarks' => ['required', 'string', 'max:3000']],
            ['remarks.required' => __('Say what ES needs to revise.')],
        );

        $this->workflow->returnPowForRevision($project, $request->user(), $validated['remarks']);

        return $this->done($project);
    }

    public function forward(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeStep($project, 'forward_pow');

        $validated = $request->validate([
            'approved_pow' => self::REQUIRED_PDF,
            'remarks' => ['nullable', 'string', 'max:3000'],
        ], ['approved_pow.required' => __('Upload the Approved POW with Project Profile.')]);

        $this->workflow->forwardApprovedPow($project, $request->user(), $request->file('approved_pow'), $validated['remarks'] ?? null);

        return $this->done($project);
    }

    private function authorizeStep(Project $project, string $stepKey): void
    {
        Gate::authorize('act', $project);
        abort_unless($project->isAt($stepKey), 403, __('This request is not at that step.'));
    }

    private function done(Project $project): RedirectResponse
    {
        return redirect()->route('dashboard')
            ->with('success', __(':ref is now with :office.', ['ref' => $project->reference, 'office' => $project->waitingOn()->label()]));
    }
}

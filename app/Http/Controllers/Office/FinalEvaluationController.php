<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Http\Requests\Office\ReturnProjectRequest;
use App\Models\Project;
use App\Services\ProjectWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * LWRD's final evaluation up to the Administrator:
 * compliance letter → the LWD's post-evaluation documents → CAF from BFMD/FS → MFTA and endorsement.
 */
class FinalEvaluationController extends Controller
{
    private const PDF = ['file', 'mimes:pdf', 'max:10240'];

    public function __construct(private ProjectWorkflow $workflow) {}

    /**
     * LWRD – send the compliance letter to the water district.
     */
    public function requestCompliance(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeStep($project, 'financial_evaluation');
        abort_if($project->complianceReceived(), 403, __('The post-evaluation documents are already in; review them instead.'));

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:3000'],
            'letter' => ['nullable', ...self::PDF],
        ], ['remarks.required' => __('Write the message to the water district.')]);

        $this->workflow->requestCompliance($project, $request->user(), $validated['remarks'], $request->file('letter'));

        return $this->done($project, __(':ref: compliance letter sent to :district.', ['ref' => $project->reference, 'district' => $project->waterDistrict->name]));
    }

    /**
     * LWRD – send the post-evaluation documents back with remarks per item.
     */
    public function returnCompliance(ReturnProjectRequest $request, Project $project): RedirectResponse
    {
        $this->workflow->returnCompliance($project, $request->user(), $request->flaggedItems(), $request->validated('remarks'));

        return $this->done($project, __(':ref: post-evaluation documents returned to :district.', ['ref' => $project->reference, 'district' => $project->waterDistrict->name]));
    }

    /**
     * LWRD – accept the post-evaluation documents and request the CAF.
     */
    public function acceptCompliance(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeStep($project, 'financial_evaluation');
        abort_unless($project->complianceReceived(), 403, __('The water district has not sent its post-evaluation documents yet.'));

        $validated = $request->validate(['remarks' => ['nullable', 'string', 'max:3000']]);

        $this->workflow->acceptCompliance($project, $request->user(), $validated['remarks'] ?? null);

        return $this->done($project);
    }

    /**
     * BFMD/FS – issue the Certificate of Availability of Funds.
     */
    public function issueCaf(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeStep($project, 'issue_caf');

        $validated = $request->validate([
            'caf' => ['required', ...self::PDF],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ], ['caf.required' => __('Upload the Certificate of Availability of Funds.')]);

        $this->workflow->issueCaf($project, $request->user(), $request->file('caf'), $validated['remarks'] ?? null);

        return $this->done($project);
    }

    /**
     * LWRD – prepare the MFTA and Memorandum, endorse to the Administrator with a recommendation.
     */
    public function endorse(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeStep($project, 'complete_evaluation');

        $validated = $request->validate([
            'recommendation' => ['required', 'in:approve,disapprove'],
            'memorandum' => ['required', ...self::PDF],
            'mfta' => ['nullable', ...self::PDF],
            'remarks' => ['required', 'string', 'max:3000'],
        ], [
            'recommendation.required' => __('Choose your recommendation.'),
            'memorandum.required' => __('Upload the Memorandum for the Administrator.'),
        ]);

        $this->workflow->endorseToAdministrator(
            $project,
            $request->user(),
            $validated['recommendation'] === 'approve',
            $request->file('memorandum'),
            $request->file('mfta'),
            $validated['remarks'],
        );

        return $this->done($project);
    }

    private function authorizeStep(Project $project, string $stepKey): void
    {
        Gate::authorize('act', $project);
        abort_unless($project->isAt($stepKey), 403, __('This request is not at that step.'));
    }

    private function done(Project $project, ?string $message = null): RedirectResponse
    {
        return redirect()->route('dashboard')->with('success', $message
            ?? __(':ref is now with :office.', ['ref' => $project->reference, 'office' => $project->waitingOn()->label()]));
    }
}

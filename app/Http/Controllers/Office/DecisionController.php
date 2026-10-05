<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\ProjectWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * From the Administrator's decision to the contract:
 * approve/disapprove → LWRD letter → LPMD endorses to Legal → Legal prepares the FAC.
 */
class DecisionController extends Controller
{
    private const PDF = ['file', 'mimes:pdf', 'max:10240'];

    public function __construct(private ProjectWorkflow $workflow) {}

    /**
     * Administrator – approve the financial assistance.
     */
    public function approve(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeStep($project, 'final_decision');

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:3000'],
            'signed_memorandum' => ['nullable', ...self::PDF],
        ]);

        $this->workflow->approveLoan($project, $request->user(), $validated['remarks'] ?? null, $request->file('signed_memorandum'));

        return $this->done($project, __(':ref approved. LWRD will send the approval letter.', ['ref' => $project->reference]));
    }

    /**
     * Administrator – disapprove the financial assistance.
     */
    public function disapprove(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeStep($project, 'final_decision');

        $validated = $request->validate(
            ['remarks' => ['required', 'string', 'max:3000']],
            ['remarks.required' => __('Give the reason for the disapproval.')],
        );

        $this->workflow->disapproveLoan($project, $request->user(), $validated['remarks']);

        return $this->done($project, __(':ref disapproved. LWRD will inform the water district.', ['ref' => $project->reference]));
    }

    /**
     * LWRD – letter informing the water district of the disapproval.
     */
    public function disapprovalLetter(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('act', $project);
        abort_unless($project->awaitsDisapprovalLetter(), 403, __('This request is not waiting for a disapproval letter.'));

        $validated = $request->validate([
            'letter' => ['required', ...self::PDF],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ], ['letter.required' => __('Upload the letter to the water district.')]);

        $this->workflow->sendDisapprovalLetter($project, $request->user(), $request->file('letter'), $validated['remarks'] ?? null);

        return $this->done($project, __(':ref: disapproval letter sent. The request is closed.', ['ref' => $project->reference]));
    }

    /**
     * LWRD – approval letter to the water district; approved MFTA to LPMD.
     */
    public function approvalLetter(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeStep($project, 'notify');

        $validated = $request->validate([
            'letter' => ['required', ...self::PDF],
            'mfta' => ['nullable', ...self::PDF],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ], ['letter.required' => __('Upload the approval letter to the water district.')]);

        $this->workflow->sendApprovalLetter($project, $request->user(), $request->file('letter'), $request->file('mfta'), $validated['remarks'] ?? null);

        return $this->done($project);
    }

    /**
     * LPMD/FS – documents secured; endorse to Legal.
     */
    public function endorseToLegal(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeStep($project, 'loan_setup');

        $validated = $request->validate([
            'documents_secured' => ['accepted'],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ], ['documents_secured.accepted' => __('Confirm that the required documents are secured.')]);

        $this->workflow->endorseToLegal($project, $request->user(), $validated['remarks'] ?? null);

        return $this->done($project);
    }

    /**
     * Legal – upload the Financial Assistance Contract; the request is completed.
     */
    public function contract(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeStep($project, 'contract');

        $validated = $request->validate([
            'contract' => ['required', ...self::PDF],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ], ['contract.required' => __('Upload the Financial Assistance Contract.')]);

        $this->workflow->completeContract($project, $request->user(), $request->file('contract'), $validated['remarks'] ?? null);

        return $this->done($project, __(':ref completed. The Financial Assistance Contract is prepared.', ['ref' => $project->reference]));
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

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
 * WSUDD's step: check the submission is complete, then endorse it or return it to the water district.
 */
class CompletenessCheckController extends Controller
{
    public function __construct(private ProjectWorkflow $workflow) {}

    public function endorse(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('act', $project);
        abort_unless($project->isAt('completeness'), 403);

        // Any amount is allowed; a regular loan's type follows it (see ProjectWorkflow::endorse()).
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ], [
            'amount.required' => __('Assign the loan amount for this request.'),
            'amount.gt' => __('The loan amount must be more than zero.'),
        ]);

        $missing = $project->submissionRequirements()
            ->reject->is_optional
            ->reject(fn ($requirement) => $project->documents()->where('requirement_id', $requirement->id)->exists());

        if ($missing->isNotEmpty()) {
            return back()->with('error', __('Cannot endorse: item(s) :items have no file. Flag them and return the request instead.', [
                'items' => $missing->pluck('number')->implode(', '),
            ]));
        }

        $this->workflow->endorse($project, $request->user(), (string) $validated['amount'], $validated['remarks'] ?? null);

        return redirect()->route('dashboard')
            ->with('success', __(':ref endorsed. It is now with :unit.', ['ref' => $project->reference, 'unit' => $project->currentStep()['unit']]));
    }

    /**
     * WSUDD disapproves the request. This ends it; no office acts on it afterwards.
     */
    public function disapprove(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('act', $project);
        abort_unless($project->isAt('completeness'), 403);

        $validated = $request->validate(
            ['remarks' => ['required', 'string', 'max:2000']],
            ['remarks.required' => __('Give the reason for disapproving the request.')],
        );

        $this->workflow->disapproveRequest($project, $request->user(), $validated['remarks']);

        return redirect()->route('dashboard')
            ->with('success', __(':ref disapproved. The request is closed.', ['ref' => $project->reference]));
    }

    public function return(ReturnProjectRequest $request, Project $project): RedirectResponse
    {
        $this->workflow->returnToWaterDistrict($project, $request->user(), $request->flaggedItems(), $request->validated('remarks'));

        return redirect()->route('dashboard')
            ->with('success', __(':ref returned to :district.', ['ref' => $project->reference, 'district' => $project->waterDistrict->name]));
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Http\Requests\SaveProjectRequest;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Services\ProjectWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectController extends Controller
{
    public function __construct(private ProjectWorkflow $workflow) {}

    /**
     * Request a Project form for the chosen project type (reached from the "Select Project Type" modal).
     */
    public function create(Request $request, ProjectType $type): View|RedirectResponse
    {
        if (! $request->user()->canRequest($type)) {
            return redirect()->route('dashboard')
                ->with('error', __(':type is available to Category C and D water districts only.', ['type' => $type->label()]));
        }

        $project = new Project(['type' => $type]);

        return view('projects.form', ['project' => $project, 'requirements' => $project->submissionRequirements()]);
    }

    public function store(SaveProjectRequest $request): RedirectResponse
    {
        $user = $request->user();

        $project = DB::transaction(function () use ($request, $user) {
            $project = Project::create([
                ...$request->safe()->only(['title', 'description', 'amount', 'funding_purpose']),
                'type' => $request->projectType(),
                'water_district_id' => $user->water_district_id,
                'user_id' => $user->id,
                'status' => ProjectStatus::Draft,
            ]);

            $this->saveDocuments($request, $project);

            return $project;
        });

        return $this->afterSave($request, $project);
    }

    public function edit(Project $project): View
    {
        Gate::authorize('update', $project);

        $project->load(['documents', 'returnedItems' => fn ($query) => $query->open()->with('log.user')]);

        return view('projects.form', ['project' => $project, 'requirements' => $project->submissionRequirements()]);
    }

    public function update(SaveProjectRequest $request, Project $project): RedirectResponse
    {
        DB::transaction(function () use ($request, $project) {
            $project->update($request->safe()->only(['title', 'description', 'amount', 'funding_purpose']));

            $project->documents()
                ->whereIn('id', $request->input('remove_documents', []))
                ->get()
                ->each->delete();

            $this->saveDocuments($request, $project);
        });

        return $this->afterSave($request, $project);
    }

    /**
     * Opens an uploaded PDF in the browser.
     */
    public function document(Request $request, ProjectDocument $document): StreamedResponse
    {
        Gate::authorize('view', $document->project);

        // Office attachments (assessments etc.) are internal to LWUA.
        abort_if($document->isInternal() && ! $request->user()->role->isLwuaOffice(), 403);

        return Storage::disk('local')->response($document->path, $document->original_name);
    }

    private function saveDocuments(SaveProjectRequest $request, Project $project): void
    {
        foreach ($request->file('documents', []) as $requirementId => $files) {
            foreach ($files as $file) {
                $project->addDocument($file, $request->user(), $requirementId);
            }
        }
    }

    private function afterSave(SaveProjectRequest $request, Project $project): RedirectResponse
    {
        if (! $request->isSubmitting()) {
            return redirect()->route('projects.edit', $project)
                ->with('success', $project->status === ProjectStatus::Returned
                    ? __('Changes saved. Resubmit when all returned items are fixed.')
                    : __('Draft saved. You can continue uploading your requirements anytime.'));
        }

        // Step 1 (LWD submits) is done; the request now waits for WSUDD's completeness check.
        $resubmitting = $project->status === ProjectStatus::Returned;
        $this->workflow->submit($project, $request->user());

        return redirect()->route('dashboard', ['project' => $project->id])
            ->with('success', $resubmitting
                ? __(':ref has been resubmitted to WSUDD with your corrections.', ['ref' => $project->reference])
                : __(':ref has been submitted to WSUDD for completeness check.', ['ref' => $project->reference]));
    }
}

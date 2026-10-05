<?php

namespace App\Http\Controllers\Office;

use App\Enums\RequirementStage;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Requirement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProjectReviewController extends Controller
{
    /**
     * A2 – Project Review: everything about one request, plus the action panel when it is this office's turn.
     */
    public function show(Request $request, Project $project): View
    {
        Gate::authorize('view', $project);

        $project->load(['waterDistrict', 'user', 'documents', 'returnedItems', 'logs.user', 'logs.documents', 'logs.returnedItems.requirement']);

        return view('office.projects.show', [
            'project' => $project,
            'canAct' => $request->user()->can('act', $project),
            'submission' => Requirement::checklist($project->type, RequirementStage::PreEvaluation)->get(),
            'lwuaDocuments' => Requirement::checklist($project->type, RequirementStage::LwuaUnit)->get(),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\RequirementStage;
use App\Enums\Role;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Everyone lands here after login; the page depends on the role.
     */
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasRole(Role::SystemAdmin)) {
            return redirect()->route('admin.overview');
        }

        return $user->hasRole(Role::Lwd)
            ? $this->waterDistrict($request, $user)
            : $this->office($request, $user);
    }

    /**
     * Water district: its projects, the Process Flow and the View Project drawer.
     */
    private function waterDistrict(Request $request, User $user): View
    {
        // A water district only ever sees its own projects.
        $projects = $user->waterDistrict?->projects()->orderByDesc('id')->get() ?? collect();

        // Option A: the Process Flow follows the clicked project, defaulting to the latest one.
        $selected = $projects->firstWhere('id', $request->integer('project')) ?? $projects->first();

        // The View Project drawer (?drawer=files|progress) opens on the selected project.
        $drawer = $selected && in_array($request->query('drawer'), ['files', 'progress'], true)
            ? $request->query('drawer')
            : null;

        if ($drawer) {
            $selected->load(['documents', 'returnedItems', 'logs.user', 'logs.documents', 'logs.returnedItems.requirement']);
        }

        return view('dashboard.lwd', [
            'projects' => $projects,
            'selected' => $selected,
            'drawer' => $drawer,
            'checklists' => $drawer ? [
                RequirementStage::PreEvaluation->value => Requirement::checklist($selected->type, RequirementStage::PreEvaluation)->get(),
                RequirementStage::LwuaUnit->value => Requirement::checklist($selected->type, RequirementStage::LwuaUnit)->get(),
            ] : [],
        ]);
    }

    /**
     * LWUA office: "My Queue" (waiting on this office) and "Handled" (this office already acted on).
     */
    private function office(Request $request, User $user): View
    {
        $tab = $request->query('tab') === 'handled' ? 'handled' : 'queue';

        $query = Project::query()
            ->with('waterDistrict')
            ->withExists('returnedItems as was_returned')
            ->withMax('logs as last_activity_at', 'created_at');

        $projects = $tab === 'queue'
            ? $query->awaiting($user->role)->orderBy('last_activity_at')->get()   // longest waiting first
            : $query->whereHas('logs.user', fn ($users) => $users->where('role', $user->role))->latest('updated_at')->get();

        return view('dashboard.office', [
            'role' => $user->role,
            'tab' => $tab,
            'projects' => $projects,
            'queueCount' => $tab === 'queue' ? $projects->count() : Project::awaiting($user->role)->count(),
        ]);
    }
}

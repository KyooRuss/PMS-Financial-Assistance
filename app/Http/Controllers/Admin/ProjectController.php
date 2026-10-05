<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\WaterDistrict;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Every request from every water district. Opening one uses the offices' review page (view only).
 */
class ProjectController extends Controller
{
    /** "In Progress" = with an office or a water district, not closed and not a draft. */
    public const IN_PROGRESS = [
        ProjectStatus::Submitted, ProjectStatus::Returned, ProjectStatus::UnderEvaluation, ProjectStatus::ForCompliance, ProjectStatus::Approved,
    ];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $reference = preg_match('/^P0*(\d+)$/i', $search, $match) ? (int) $match[1] : null;

        $projects = Project::query()
            ->with('waterDistrict')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('title', 'like', "%{$search}%")
                ->when($reference, fn ($query) => $query->orWhere('id', $reference))))
            ->when(ProjectStatus::tryFrom((string) $request->query('status')), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('status') === 'in_progress', fn ($query) => $query->whereIn('status', self::IN_PROGRESS))
            ->when(ProjectType::tryFrom((string) $request->query('type')), fn ($query, $type) => $query->where('type', $type))
            ->when(Role::tryFrom((string) $request->query('waiting')), fn ($query, $role) => $query->awaiting($role)->where('status', '!=', ProjectStatus::Draft))
            ->when($request->integer('district'), fn ($query, $district) => $query->where('water_district_id', $district))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.projects', [
            'projects' => $projects,
            'districts' => WaterDistrict::orderBy('name')->get(),
            'offices' => collect(Role::cases())->reject(fn (Role $role) => $role === Role::SystemAdmin),
        ]);
    }
}

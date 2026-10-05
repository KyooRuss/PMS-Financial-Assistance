<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectLog;
use App\Models\User;
use App\Models\WaterDistrict;
use App\Support\ProcessingTimes;
use Illuminate\View\View;

/**
 * The System Admin's home: PMS at a glance.
 */
class OverviewController extends Controller
{
    public function __invoke(): View
    {
        $byStatus = Project::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $byType = Project::query()->where('status', '!=', ProjectStatus::Draft)
            ->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');

        $inProgress = collect([ProjectStatus::Submitted, ProjectStatus::Returned, ProjectStatus::UnderEvaluation, ProjectStatus::ForCompliance, ProjectStatus::Approved])
            ->sum(fn (ProjectStatus $status) => $byStatus[$status->value] ?? 0);

        return view('admin.overview', [
            'statuses' => collect(ProjectStatus::cases())->map(fn (ProjectStatus $status) => ['status' => $status, 'total' => (int) ($byStatus[$status->value] ?? 0)]),
            'types' => collect(ProjectType::cases())->map(fn (ProjectType $type) => ['type' => $type, 'total' => (int) ($byType[$type->value] ?? 0)]),
            'submitted' => $byStatus->except(ProjectStatus::Draft->value)->sum(),
            'inProgress' => $inProgress,
            'completed' => (int) ($byStatus[ProjectStatus::Completed->value] ?? 0),
            'activeAccounts' => User::where('is_active', true)->count(),
            'inactiveAccounts' => User::where('is_active', false)->count(),
            'waterDistricts' => WaterDistrict::count(),
            'waiting' => (new ProcessingTimes)->waiting(),
            'recent' => ProjectLog::with(['project.waterDistrict', 'user'])->latest()->latest('id')->limit(10)->get(),
        ]);
    }
}

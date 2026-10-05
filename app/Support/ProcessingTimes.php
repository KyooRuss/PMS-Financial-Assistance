<?php

namespace App\Support;

use App\Enums\FundingPurpose;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\Role;
use App\Models\Project;
use App\Models\ProjectLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * How long each office holds a request, measured from the timeline.
 *
 * Every action writes a timeline entry, so the office that acted held the request from the
 * previous entry until its own. That also covers the time the water district takes to fix a
 * return or send its post-evaluation documents, and repeat visits (a POW revision, a second
 * compliance round) count as separate handlings.
 */
class ProcessingTimes
{
    /** Departments in the order they appear in the flow; the water district last. */
    private const DEPARTMENT_ORDER = [
        Role::Wsudd, Role::Es, Role::Lwrd, Role::Cpic, Role::Administrator, Role::Bfmd, Role::Lpmd, Role::Legal, Role::Lwd,
    ];

    /** @var Collection<int, Project> */
    private Collection $projects;

    /**
     * @param  'regular'|'emergency'|null  $flow
     * @param  Carbon|null  $since  only handlings that ended on or after this date
     */
    public function __construct(private ?string $flow = null, private ?Carbon $since = null)
    {
        $this->projects = Project::query()
            ->when($flow === 'regular', fn ($query) => $query->where('type', '!=', ProjectType::Emergency))
            ->when($flow === 'emergency', fn ($query) => $query->where('type', ProjectType::Emergency))
            ->where('status', '!=', ProjectStatus::Draft)
            ->with(['logs' => fn ($query) => $query->reorder()->oldest()->orderBy('id')])
            ->get();
    }

    /**
     * One row per time an office held a request.
     *
     * @return Collection<int, array{project: Project, role: Role, step_key: string, label: string, hours: float, ended_at: Carbon}>
     */
    public function handlings(): Collection
    {
        return $this->projects->flatMap(function (Project $project) {
            $steps = $project->steps();
            $logs = $project->logs->values();

            return $logs->slice(1)->map(function (ProjectLog $log, int $i) use ($logs, $steps, $project) {
                $step = $steps[$log->step - 1] ?? null;

                if (! $step || ($this->since && $log->created_at->lt($this->since))) {
                    return null;
                }

                $role = ProcessFlow::roleFor($log->office);

                return [
                    'project' => $project,
                    'role' => $role,
                    'step_key' => $step['key'],
                    'label' => $this->stepLabel($step, $role),
                    'hours' => $logs[$i - 1]->created_at->diffInMinutes($log->created_at) / 60,
                    'ended_at' => $log->created_at,
                ];
            })->filter();
        })->values();
    }

    /**
     * Average, longest and count per department, in flow order.
     *
     * @return Collection<int, array{role: Role, average: float, longest: float, count: int}>
     */
    public function byDepartment(): Collection
    {
        $handlings = $this->handlings()->groupBy(fn ($handling) => $handling['role']->value);

        return collect(self::DEPARTMENT_ORDER)
            ->filter(fn (Role $role) => $handlings->has($role->value))
            ->map(fn (Role $role) => $this->summarize($handlings[$role->value]) + ['role' => $role])
            ->values();
    }

    /**
     * Average, longest and count per step of each flow, in step order.
     *
     * @return array<string, Collection<int, array{label: string, role: Role, average: float, longest: float, count: int}>>
     */
    public function bySteps(): array
    {
        $flows = [
            'regular' => ProcessFlow::steps(ProjectType::Regular50MBelow),
            'emergency' => ProcessFlow::steps(ProjectType::Emergency, FundingPurpose::Capex),
        ];

        $handlings = $this->handlings()->groupBy(fn ($h) => ($h['project']->type->isEmergency() ? 'emergency' : 'regular'));

        return collect($flows)
            ->reject(fn ($steps, $flow) => $this->flow && $this->flow !== $flow)
            ->map(function (array $steps, string $flow) use ($handlings) {
                $rows = collect($handlings->get($flow, []))->groupBy(fn ($h) => $h['step_key'].'|'.$h['role']->value);
                $order = array_flip(array_column($steps, 'key'));

                return $rows
                    ->map(fn ($group) => $this->summarize($group) + ['label' => $group[0]['label'], 'role' => $group[0]['role'], 'key' => $group[0]['step_key']])
                    // In step order; the water district's time on a step comes right after the office's.
                    ->sortBy(fn ($row) => [$order[$row['key']] ?? 99, $row['role'] === Role::Lwd ? 1 : 0])
                    ->values();
            })
            ->all();
    }

    /**
     * Requests waiting right now, per department: how many and for how long.
     *
     * @return Collection<int, array{role: Role, count: int, average: float, longest: float}>
     */
    public function waiting(): Collection
    {
        $now = now();
        $open = $this->projects
            ->filter(fn (Project $project) => $project->current_role !== null && $project->logs->isNotEmpty())
            ->groupBy(fn (Project $project) => $project->current_role->value);

        return collect(self::DEPARTMENT_ORDER)
            ->filter(fn (Role $role) => $open->has($role->value))
            ->map(function (Role $role) use ($open, $now) {
                $hours = $open[$role->value]->map(fn (Project $project) => $project->logs->last()->created_at->diffInMinutes($now) / 60);

                return ['role' => $role, 'count' => $hours->count(), 'average' => $hours->avg(), 'longest' => $hours->max()];
            })
            ->values();
    }

    /**
     * Average time from submission to the Financial Assistance Contract, for completed requests.
     */
    public function averageCompletionHours(): ?float
    {
        $completed = $this->projects
            ->filter(fn (Project $project) => $project->status === ProjectStatus::Completed && $project->logs->count() > 1)
            ->filter(fn (Project $project) => ! $this->since || $project->logs->last()->created_at->gte($this->since));

        return $completed->isEmpty() ? null : $completed->avg(
            fn (Project $project) => $project->logs->first()->created_at->diffInMinutes($project->logs->last()->created_at) / 60
        );
    }

    public function completedCount(): int
    {
        return $this->projects->where('status', ProjectStatus::Completed)
            ->filter(fn (Project $project) => ! $this->since || $project->logs->last()?->created_at->gte($this->since))
            ->count();
    }

    /**
     * Whole numbers: "12 days", "1 day", or hours when under a day ("5 h").
     */
    public static function format(?float $hours): string
    {
        return match (true) {
            $hours === null => '—',
            $hours < 24 => __(':n h', ['n' => max(1, (int) round($hours))]),
            default => trans_choice(':count day|:count days', (int) round($hours / 24)),
        };
    }

    /**
     * @param  Collection<int, array{hours: float}>  $group
     * @return array{average: float, longest: float, count: int}
     */
    private function summarize(Collection $group): array
    {
        return [
            'average' => $group->avg('hours'),
            'longest' => $group->max('hours'),
            'count' => $group->count(),
        ];
    }

    /**
     * "CPIC – Funding / prioritization", or what the water district was doing on another office's step.
     */
    private function stepLabel(array $step, Role $role): string
    {
        if ($role === ProcessFlow::roleFor($step['unit'])) {
            return "{$step['unit']} – {$step['action']}";
        }

        return match (true) {
            $step['key'] === 'completeness' => __('LWD – Fixing returned items'),
            $step['key'] === 'financial_evaluation' => __('LWD – Post-evaluation documents'),
            $step['key'] === 'final_decision' && $role === Role::Lwrd => __('LWRD – Disapproval letter'),
            default => "{$role->label()} – {$step['action']}",
        };
    }
}

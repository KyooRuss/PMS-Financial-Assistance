<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Support\ProcessingTimes;
use Illuminate\View\View;

/**
 * Each LWUA office: its staff, what is waiting on it now, and how long it usually takes.
 */
class DepartmentController extends Controller
{
    public function index(): View
    {
        $times = new ProcessingTimes;
        $averages = $times->byDepartment()->keyBy(fn ($row) => $row['role']->value);
        $waiting = $times->waiting()->keyBy(fn ($row) => $row['role']->value);
        $staff = User::query()->whereNot('role', Role::Lwd)->orderBy('last_name')->get()->groupBy(fn (User $user) => $user->role->value);

        $departments = collect(Role::cases())
            ->filter(fn (Role $role) => $role->isLwuaOffice() && $role !== Role::SystemAdmin)
            ->map(fn (Role $role) => [
                'role' => $role,
                'staff' => $staff->get($role->value, collect()),
                'waiting' => $waiting->get($role->value),
                'average' => $averages->get($role->value),
                'handled' => Project::whereHas('logs.user', fn ($users) => $users->where('role', $role))->count(),
            ])
            ->values();

        return view('admin.departments', ['departments' => $departments]);
    }
}

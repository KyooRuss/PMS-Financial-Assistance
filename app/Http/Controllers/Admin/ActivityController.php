<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProjectLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Every action by everyone, across all projects (the timelines combined).
 */
class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $from = rescue(fn () => $request->filled('from') ? Carbon::parse($request->query('from'))->startOfDay() : null, null, false);
        $to = rescue(fn () => $request->filled('to') ? Carbon::parse($request->query('to'))->endOfDay() : null, null, false);

        $logs = ProjectLog::query()
            ->with(['project.waterDistrict', 'user'])
            ->when($request->filled('office'), fn (Builder $query) => $query->where('office', $request->query('office')))
            ->when($search !== '', fn (Builder $query) => $query->whereHas('user', fn (Builder $user) => $user
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($from, fn (Builder $query) => $query->where('created_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->where('created_at', '<=', $to))
            ->latest()
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.activity', [
            'logs' => $logs,
            'offices' => ProjectLog::query()->distinct()->orderBy('office')->pluck('office'),
        ]);
    }
}

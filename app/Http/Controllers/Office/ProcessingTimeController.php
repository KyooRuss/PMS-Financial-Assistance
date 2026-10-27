<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Support\ProcessingTimes;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Processing Time report: how long each department takes, so LWUA can see where requests slow down.
 */
class ProcessingTimeController extends Controller
{
    /** Period filter => days back (null = all time). */
    public const PERIODS = ['30' => 30, '90' => 90, '365' => 365, 'all' => null];

    public function __invoke(Request $request): View
    {
        $flow = in_array($request->query('flow'), ['regular', 'emergency'], true) ? $request->query('flow') : null;
        $period = array_key_exists((string) $request->query('period'), self::PERIODS) ? (string) $request->query('period') : 'all';
        $days = self::PERIODS[$period];

        $times = new ProcessingTimes($flow, $days ? now()->subDays($days)->startOfDay() : null);

        return view('office.processing-time', [
            'flow' => $flow,
            'period' => $period,
            'offices' => $times->perOffice(),
            'steps' => $times->bySteps(),
            'waiting' => $times->waiting(),
            'completionHours' => $times->averageCompletionHours(),
            'completedCount' => $times->completedCount(),
        ]);
    }
}

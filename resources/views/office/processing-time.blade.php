@php
    $fmt = fn ($hours) => \App\Support\ProcessingTimes::format($hours);

    // Horizontal bars in days, on a whole-number axis (0, 2, 4, ... or 0, 5, 10, ...).
    $maxDays = max(1, ($departments->max('average') ?? 0) / 24);
    $tickStep = collect([1, 2, 5, 10, 20, 50, 100])->first(fn ($step) => $maxDays / $step <= 5) ?? 100;
    $axisMax = ceil($maxDays / $tickStep) * $tickStep;
    $ticks = range(0, $axisMax, $tickStep);

    $slowest = $departments->where('role', '!=', \App\Enums\Role::Lwd)->sortByDesc('average')->first();
    $waitingByRole = $waiting->keyBy(fn ($row) => $row['role']->value);
    $inProgress = $waiting->sum('count');
    $flowNames = ['regular' => __('Regular loans'), 'emergency' => __('Emergency loans')];
@endphp

<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-lwua-navy">{{ __('Processing Time') }}</h1>
            <p class="mt-1 max-w-3xl text-sm text-gray-500">
                {{ __('How long each department holds a request before acting on it, measured from the timeline. Each visit counts separately, so a returned POW or a second compliance round adds a new handling.') }}
            </p>
        </div>

        {{-- Filters: one row, scoping everything below --}}
        <form method="GET" action="{{ route('reports.processing-time') }}" class="flex flex-wrap items-end gap-4">
            <div>
                <label for="period" class="block text-xs font-medium text-gray-600">{{ __('Period') }}</label>
                <select id="period" name="period" onchange="this.form.submit()" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">
                    @foreach (['30' => __('Last 30 days'), '90' => __('Last 90 days'), '365' => __('Last 12 months'), 'all' => __('All time')] as $value => $label)
                        <option value="{{ $value }}" @selected($period === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="flow" class="block text-xs font-medium text-gray-600">{{ __('Loan type') }}</label>
                <select id="flow" name="flow" onchange="this.form.submit()" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">
                    <option value="" @selected(! $flow)>{{ __('All loans') }}</option>
                    <option value="regular" @selected($flow === 'regular')>{{ __('Regular loans') }}</option>
                    <option value="emergency" @selected($flow === 'emergency')>{{ __('Emergency loans') }}</option>
                </select>
            </div>
            <noscript><button type="submit" class="rounded-md bg-lwua-dark px-4 py-2 text-sm text-white">{{ __('Apply') }}</button></noscript>
        </form>

        {{-- Summary tiles --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg bg-white p-5 shadow-md ring-1 ring-black/5">
                <p class="text-sm text-gray-500">{{ __('Average submission to contract') }}</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $fmt($completionHours) }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ trans_choice(':count completed request|:count completed requests', $completedCount) }}</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-md ring-1 ring-black/5">
                <p class="text-sm text-gray-500">{{ __('Slowest department') }}</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $slowest ? $slowest['role']->shortLabel() : '—' }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ $slowest ? __(':avg on average per handling', ['avg' => $fmt($slowest['average'])]) : __('No handlings in this period') }}</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-md ring-1 ring-black/5">
                <p class="text-sm text-gray-500">{{ __('Requests in progress') }}</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $inProgress }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ __('Waiting on an office or a water district now') }}</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-md ring-1 ring-black/5">
                <p class="text-sm text-gray-500">{{ __('Longest current wait') }}</p>
                @php($longestWait = $waiting->sortByDesc('longest')->first())
                <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $fmt($longestWait['longest'] ?? null) }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ $longestWait ? __('With :office', ['office' => $longestWait['role']->shortLabel()]) : __('Nothing waiting') }}</p>
            </div>
        </div>

        {{-- Chart: average time per handling, by department --}}
        <section class="rounded-lg bg-white p-6 shadow-md ring-1 ring-black/5">
            <h2 class="text-lg font-semibold text-lwua-navy">{{ __('Average time per handling, by department') }}</h2>
            <p class="text-xs text-gray-500">{{ __('Days from when a request reaches the department until it acts. Hover or focus a bar for details.') }}</p>

            @if ($departments->isEmpty())
                <p class="mt-6 text-sm text-gray-500">{{ __('No handlings in this period yet.') }}</p>
            @else
                <div class="mt-6" x-data="{ tip: null }">
                    <div class="space-y-3">
                        @foreach ($departments as $row)
                            @php($days = $row['average'] / 24)
                            <div class="grid grid-cols-[8rem_1fr] items-center gap-3 sm:grid-cols-[10rem_1fr]">
                                <span class="truncate text-right text-sm text-gray-700">{{ $row['role']->shortLabel() }}</span>
                                <div class="relative flex items-center gap-2">
                                    {{-- The whole row is the hit target, larger than the bar itself --}}
                                    <div class="relative h-6 flex-1 cursor-default rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-lwua-blue"
                                         tabindex="0"
                                         role="img"
                                         aria-label="{{ $row['role']->label() }}: {{ __('average :avg, longest :max, :n handlings', ['avg' => $fmt($row['average']), 'max' => $fmt($row['longest']), 'n' => $row['count']]) }}"
                                         x-on:mouseenter="tip = @js($row['role']->value)" x-on:mouseleave="tip = null"
                                         x-on:focus="tip = @js($row['role']->value)" x-on:blur="tip = null">
                                        <div class="absolute inset-y-0 left-0 flex items-center" style="width: {{ $axisMax > 0 ? round($days / $axisMax * 100, 2) : 0 }}%">
                                            <div class="h-5 w-full rounded-r bg-lwua-blue transition-[filter]" :class="tip === @js($row['role']->value) && 'brightness-110'"></div>
                                        </div>

                                        {{-- Tooltip: value first, label second --}}
                                        <div x-show="tip === @js($row['role']->value)" x-cloak
                                             class="pointer-events-none absolute bottom-full left-0 z-10 mb-2 w-56 rounded-md bg-white px-3 py-2 text-xs shadow-lg ring-1 ring-black/10">
                                            <p class="text-sm font-semibold text-gray-900">{{ __(':avg average', ['avg' => $fmt($row['average'])]) }}</p>
                                            <p class="text-gray-600">{{ $row['role']->label() }}</p>
                                            <p class="mt-1 text-gray-500">{{ __('Longest :max · :n handlings', ['max' => $fmt($row['longest']), 'n' => $row['count']]) }}</p>
                                        </div>
                                    </div>
                                    <span class="w-20 shrink-0 text-sm tabular-nums text-gray-700">{{ $fmt($row['average']) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- X axis --}}
                    <div class="mt-2 grid grid-cols-[8rem_1fr] gap-3 sm:grid-cols-[10rem_1fr]">
                        <span></span>
                        <div class="flex gap-2">
                            <div class="relative h-5 flex-1 border-t border-gray-200">
                                @foreach ($ticks as $tick)
                                    <span class="absolute top-1 -translate-x-1/2 text-[11px] tabular-nums text-gray-500" style="left: {{ $axisMax > 0 ? round($tick / $axisMax * 100, 2) : 0 }}%">{{ (int) $tick }}</span>
                                @endforeach
                            </div>
                            <span class="w-20 shrink-0 text-[11px] text-gray-500">{{ __('days') }}</span>
                        </div>
                    </div>
                </div>
            @endif
        </section>

        {{-- Table view of the same numbers, plus what is waiting now --}}
        <section class="overflow-x-auto rounded-lg bg-white shadow-md ring-1 ring-black/5">
            <div class="px-6 pt-5">
                <h2 class="text-lg font-semibold text-lwua-navy">{{ __('By department') }}</h2>
            </div>
            <table class="mt-3 min-w-full text-sm">
                <thead class="border-y border-gray-200 bg-gray-50 text-xs font-semibold text-gray-600">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left">{{ __('Department') }}</th>
                        <th scope="col" class="px-6 py-3 text-right">{{ __('Average') }}</th>
                        <th scope="col" class="px-6 py-3 text-right">{{ __('Longest') }}</th>
                        <th scope="col" class="px-6 py-3 text-right">{{ __('Handlings') }}</th>
                        <th scope="col" class="px-6 py-3 text-right">{{ __('Waiting now') }}</th>
                        <th scope="col" class="px-6 py-3 text-right">{{ __('Longest current wait') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 tabular-nums">
                    @forelse ($departments as $row)
                        @php($now = $waitingByRole->get($row['role']->value))
                        <tr>
                            <td class="px-6 py-3 text-gray-900">{{ $row['role']->label() }}</td>
                            <td @class(['px-6 py-3 text-right', 'font-semibold text-gray-900' => $slowest && $row['role'] === $slowest['role'], 'text-gray-700' => ! ($slowest && $row['role'] === $slowest['role'])])>{{ $fmt($row['average']) }}</td>
                            <td class="px-6 py-3 text-right text-gray-700">{{ $fmt($row['longest']) }}</td>
                            <td class="px-6 py-3 text-right text-gray-700">{{ $row['count'] }}</td>
                            <td class="px-6 py-3 text-right text-gray-700">{{ $now['count'] ?? 0 }}</td>
                            <td class="px-6 py-3 text-right text-gray-700">{{ $fmt($now['longest'] ?? null) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-6 text-center text-gray-500">{{ __('No handlings in this period yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        {{-- Per step, for each flow --}}
        @foreach ($steps as $flowKey => $rows)
            @php($stepMax = max(1, $rows->max('average') ?? 1))
            <section class="overflow-x-auto rounded-lg bg-white shadow-md ring-1 ring-black/5">
                <div class="px-6 pt-5">
                    <h2 class="text-lg font-semibold text-lwua-navy">{{ __('By step · :flow', ['flow' => $flowNames[$flowKey]]) }}</h2>
                    <p class="text-xs text-gray-500">{{ __('Water district rows show the time spent fixing returned items or preparing post-evaluation documents.') }}</p>
                </div>
                <table class="mt-3 min-w-full text-sm">
                    <thead class="border-y border-gray-200 bg-gray-50 text-xs font-semibold text-gray-600">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left">{{ __('Step') }}</th>
                            <th scope="col" class="w-1/4 px-6 py-3 text-left"><span class="sr-only">{{ __('Relative average') }}</span></th>
                            <th scope="col" class="px-6 py-3 text-right">{{ __('Average') }}</th>
                            <th scope="col" class="px-6 py-3 text-right">{{ __('Longest') }}</th>
                            <th scope="col" class="px-6 py-3 text-right">{{ __('Handlings') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 tabular-nums">
                        @forelse ($rows as $row)
                            <tr>
                                <td @class(['px-6 py-3', 'text-gray-900' => $row['role'] !== \App\Enums\Role::Lwd, 'pl-10 text-gray-500' => $row['role'] === \App\Enums\Role::Lwd])>{{ $row['label'] }}</td>
                                <td class="px-6 py-3" aria-hidden="true">
                                    <div class="h-2 rounded-r bg-lwua-blue" style="width: {{ round($row['average'] / $stepMax * 100, 2) }}%"></div>
                                </td>
                                <td class="px-6 py-3 text-right text-gray-900">{{ $fmt($row['average']) }}</td>
                                <td class="px-6 py-3 text-right text-gray-700">{{ $fmt($row['longest']) }}</td>
                                <td class="px-6 py-3 text-right text-gray-700">{{ $row['count'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-6 text-center text-gray-500">{{ __('No handlings in this period yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        @endforeach
    </div>
</x-app-layout>

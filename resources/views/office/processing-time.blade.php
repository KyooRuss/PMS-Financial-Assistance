@php
    $fmt = fn ($hours) => \App\Support\ProcessingTimes::format($hours);

    // The slowest LWUA office is highlighted, once there are at least two to compare; the water districts' own time is not ranked.
    $ranked = $offices->where('role', '!=', \App\Enums\Role::Lwd)->whereNotNull('average');
    $slowest = $ranked->count() > 1 ? $ranked->sortByDesc('average')->first() : null;
    $longestWait = $waiting->sortByDesc('longest')->first();
    $flowNames = ['regular' => __('Regular loans'), 'emergency' => __('Emergency loans')];
@endphp

<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-lwua-navy">{{ __('Processing Time') }}</h1>
            <p class="mt-1 max-w-3xl text-sm text-gray-500">
                {{ __('How many days each office takes to act on a request, from the day it arrives until the office acts.') }}
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
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-lg bg-white p-5 shadow-md ring-1 ring-black/5">
                <p class="text-sm text-gray-500">{{ __('Average from submission to contract') }}</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $fmt($completionHours) }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ trans_choice(':count completed request|:count completed requests', $completedCount) }}</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-md ring-1 ring-black/5">
                <p class="text-sm text-gray-500">{{ __('Requests in progress') }}</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $waiting->sum('count') }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ __('Waiting on an office or a water district') }}</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-md ring-1 ring-black/5">
                <p class="text-sm text-gray-500">{{ __('Longest wait right now') }}</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $fmt($longestWait['longest'] ?? null) }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ $longestWait ? __('At :office', ['office' => $longestWait['role']->shortLabel()]) : __('Nothing waiting') }}</p>
            </div>
        </div>

        {{-- One row per office: average days, plus how many requests wait there now --}}
        <section class="overflow-x-auto rounded-lg bg-white shadow-md ring-1 ring-black/5">
            <div class="px-6 pt-5">
                <h2 class="text-lg font-semibold text-lwua-navy">{{ __('Average days per office') }}</h2>
            </div>
            <table class="mt-3 min-w-full text-sm">
                <thead class="border-y border-gray-200 bg-gray-50 text-xs font-semibold text-gray-600">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left">{{ __('Office') }}</th>
                        <th scope="col" class="px-6 py-3 text-right">{{ __('Average days') }}</th>
                        <th scope="col" class="px-6 py-3 text-right">{{ __('Waiting now') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 tabular-nums">
                    @forelse ($offices as $row)
                        @php($isSlowest = $slowest && $row['role'] === $slowest['role'])
                        <tr @class(['bg-red-50/40' => $isSlowest])>
                            <td class="px-6 py-3">
                                <span class="font-medium text-gray-900">{{ $row['role']->shortLabel() }}</span>
                                @if ($row['role'] === \App\Enums\Role::Lwd)
                                    <span class="block text-xs text-gray-500">{{ __('Fixing returns & sending documents') }}</span>
                                @else
                                    <span class="block text-xs text-gray-500">{{ $row['role']->label() }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-right">
                                <span @class(['font-semibold text-red-700' => $isSlowest, 'text-gray-900' => ! $isSlowest])>{{ $fmt($row['average']) }}</span>
                                @if ($isSlowest)
                                    <span class="ml-2 rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-700 ring-1 ring-red-100">{{ __('Slowest') }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-right text-gray-700">{{ $row['waiting'] ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-6 py-6 text-center text-gray-500">{{ __('Nothing to show for this period yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        {{-- Step-by-step averages, hidden until asked for --}}
        <details class="group rounded-lg bg-white shadow-md ring-1 ring-black/5">
            <summary class="flex cursor-pointer list-none items-center gap-2 px-6 py-4 text-sm font-semibold text-lwua-navy hover:text-lwua-blue [&::-webkit-details-marker]:hidden">
                <svg class="h-4 w-4 transition-transform group-open:rotate-90" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
                {{ __('Show step-by-step details') }}
            </summary>

            <div class="grid gap-6 border-t border-gray-100 px-6 py-5 lg:grid-cols-2">
                @foreach ($steps as $flowKey => $rows)
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">{{ $flowNames[$flowKey] }}</h3>
                        <table class="mt-2 min-w-full text-sm">
                            <thead class="border-b border-gray-200 text-xs font-semibold text-gray-600">
                                <tr>
                                    <th scope="col" class="py-2 text-left">{{ __('Step') }}</th>
                                    <th scope="col" class="py-2 text-right">{{ __('Average days') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 tabular-nums">
                                @forelse ($rows as $row)
                                    <tr>
                                        <td @class(['py-2 pr-4', 'text-gray-900' => $row['role'] !== \App\Enums\Role::Lwd, 'pl-4 text-gray-500' => $row['role'] === \App\Enums\Role::Lwd])>{{ $row['label'] }}</td>
                                        <td class="py-2 text-right text-gray-900">{{ $fmt($row['average']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="py-4 text-center text-gray-500">{{ __('Nothing to show for this period yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
        </details>
    </div>
</x-app-layout>

@php
    $statusMax = max(1, $statuses->max('total'));
@endphp

<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        @include('admin.partials.heading', ['title' => __('Overview'), 'subtitle' => __('PMS at a glance: requests, accounts and what each office is holding right now.')])

        {{-- Summary tiles --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                [__('Requests submitted'), $submitted, __('All time, drafts excluded'), route('admin.projects.index')],
                [__('In progress'), $inProgress, __('With an office or a water district'), route('admin.projects.index')],
                [__('Completed'), $completed, __('Financial Assistance Contract prepared'), route('admin.projects.index', ['status' => 'completed'])],
                [__('Active accounts'), $activeAccounts, trans_choice(':count inactive|:count inactive', $inactiveAccounts).' · '.trans_choice(':count water district|:count water districts', $waterDistricts), route('admin.users.index')],
            ] as [$label, $value, $note, $link])
                <a href="{{ $link }}" class="rounded-lg bg-white p-5 shadow-md ring-1 ring-black/5 hover:ring-lwua-blue/40">
                    <p class="text-sm text-gray-500">{{ $label }}</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900">{{ number_format($value) }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ $note }}</p>
                </a>
            @endforeach
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Requests by status --}}
            <section class="rounded-lg bg-white p-6 shadow-md ring-1 ring-black/5">
                <h2 class="text-lg font-semibold text-lwua-navy">{{ __('Requests by status') }}</h2>
                <ul class="mt-4 space-y-2">
                    @foreach ($statuses as $row)
                        <li>
                            <a href="{{ route('admin.projects.index', ['status' => $row['status']->value]) }}" class="grid grid-cols-[9.5rem_1fr_2.5rem] items-center gap-3 rounded px-1 py-0.5 hover:bg-gray-50">
                                <span class="text-sm text-gray-700">{{ $row['status']->label() }}</span>
                                <span class="h-4 rounded-r bg-lwua-blue" style="width: {{ $row['total'] ? max(2, round($row['total'] / $statusMax * 100, 2)) : 0 }}%" aria-hidden="true"></span>
                                <span class="text-right text-sm tabular-nums text-gray-900">{{ $row['total'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                    @foreach ($types as $row)
                        <span>{{ $row['type']->label() }}: <span class="font-semibold text-gray-700">{{ $row['total'] }}</span></span>
                    @endforeach
                </p>
            </section>

            {{-- Waiting right now --}}
            <section class="rounded-lg bg-white p-6 shadow-md ring-1 ring-black/5">
                <div class="flex items-baseline justify-between gap-4">
                    <h2 class="text-lg font-semibold text-lwua-navy">{{ __('Waiting right now') }}</h2>
                    <a href="{{ route('reports.processing-time') }}" class="text-sm font-medium text-lwua-blue hover:underline">{{ __('Processing Time') }} &rarr;</a>
                </div>
                @if ($waiting->isEmpty())
                    <p class="mt-4 text-sm text-gray-500">{{ __('Nothing is waiting.') }}</p>
                @else
                    <table class="mt-3 min-w-full text-sm">
                        <thead class="text-xs font-semibold text-gray-600">
                            <tr>
                                <th scope="col" class="py-2 text-left">{{ __('Office') }}</th>
                                <th scope="col" class="py-2 text-right">{{ __('Requests') }}</th>
                                <th scope="col" class="py-2 text-right">{{ __('Longest wait') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 tabular-nums">
                            @foreach ($waiting as $row)
                                <tr>
                                    <td class="py-2">
                                        <a href="{{ route('admin.projects.index', ['waiting' => $row['role']->value]) }}" class="text-gray-900 hover:text-lwua-blue hover:underline">{{ $row['role']->shortLabel() }}</a>
                                    </td>
                                    <td class="py-2 text-right text-gray-900">{{ $row['count'] }}</td>
                                    <td class="py-2 text-right text-gray-700">{{ \App\Support\ProcessingTimes::format($row['longest']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        </div>

        {{-- Recent activity --}}
        <section class="rounded-lg bg-white p-6 shadow-md ring-1 ring-black/5">
            <h2 class="text-lg font-semibold text-lwua-navy">{{ __('Recent activity') }}</h2>
            @include('admin.partials.activity-list', ['logs' => $recent])
        </section>
    </div>
</x-app-layout>

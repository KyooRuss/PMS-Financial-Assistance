<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        {{-- Filtered by "Waiting on", the description says what that office does with a request. --}}
        @php($waitingOn = \App\Enums\Role::tryFrom((string) request('waiting')))
        @include('admin.partials.heading', [
            'title' => __('All Projects'),
            'subtitle' => $waitingOn
                ? __('Requests waiting on :office right now.', ['office' => $waitingOn->label()]).' '.__($waitingOn->responsibility())
                : __('Every request from every water district. Click View to see its details; you can view but not act on it.'),
        ])

        {{-- Filters apply as soon as an option is picked; the search box applies on Enter or when you leave it. --}}
        <form method="GET" class="flex flex-wrap items-end gap-3" x-data x-on:change="$el.submit()" x-on:search="$el.submit()">
            <div>
                <label for="q" class="block text-xs font-medium text-gray-600">{{ __('Search') }}</label>
                <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="{{ __('P001 or Category A') }}"
                       class="mt-1 w-56 rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">
            </div>
            <div>
                <label for="status" class="block text-xs font-medium text-gray-600">{{ __('Status') }}</label>
                <select id="status" name="status" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">
                    <option value="">{{ __('All') }}</option>
                    <option value="in_progress" @selected(request('status') === 'in_progress')>{{ __('In progress (all active)') }}</option>
                    @foreach (\App\Enums\ProjectStatus::cases() as $status)
                        @continue($status === \App\Enums\ProjectStatus::Draft)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="type" class="block text-xs font-medium text-gray-600">{{ __('Type') }}</label>
                <select id="type" name="type" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">
                    <option value="">{{ __('All') }}</option>
                    @foreach (\App\Enums\ProjectType::cases() as $type)
                        <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->shortLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="waiting" class="block text-xs font-medium text-gray-600">{{ __('Waiting on') }}</label>
                <select id="waiting" name="waiting" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">
                    <option value="">{{ __('Anyone') }}</option>
                    @foreach ($offices as $office)
                        <option value="{{ $office->value }}" @selected(request('waiting') === $office->value)>{{ $office->shortLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="district" class="block text-xs font-medium text-gray-600">{{ __('Water district') }}</label>
                <select id="district" name="district" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($districts as $district)
                        <option value="{{ $district->id }}" @selected((int) request('district') === $district->id)>{{ $district->name }}</option>
                    @endforeach
                </select>
            </div>
            @if (request()->hasAny(['q', 'status', 'type', 'waiting', 'district']))
                <a href="{{ route('admin.projects.index') }}" class="py-2 text-sm text-gray-500 hover:text-gray-800">{{ __('Clear') }}</a>
            @endif
        </form>

        <div class="overflow-x-auto rounded-lg bg-white shadow-md ring-1 ring-black/5">
            <table class="min-w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs font-semibold text-gray-600">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left">{{ __('Project ID') }}</th>
                        <th scope="col" class="px-6 py-3 text-left">{{ __('Water District') }}</th>
                        <th scope="col" class="px-6 py-3 text-left">{{ __('Type') }}</th>
                        <th scope="col" class="px-6 py-3 text-left">{{ __('Details') }}</th>
                        <th scope="col" class="px-6 py-3 text-left">{{ __('Status') }}</th>
                        <th scope="col" class="px-6 py-3 text-left">{{ __('Progress') }}</th>
                        <th scope="col" class="px-6 py-3 text-left">{{ __('Submitted') }}</th>
                        <th scope="col" class="px-6 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($projects as $project)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3 font-bold text-lwua-navy">{{ $project->reference }}</td>
                            <td class="px-6 py-3">
                                <p class="text-gray-800">{{ $project->waterDistrict->name }}</p>
                                <p class="text-xs text-gray-500">{{ __('Category :c', ['c' => $project->waterDistrict->category]) }}</p>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-gray-800">
                                {{ $project->type->shortLabel() }}
                                @if ($project->funding_purpose) <span class="text-xs text-gray-500">({{ $project->funding_purpose->label() }})</span> @endif
                            </td>
                            <td class="max-w-xs px-6 py-3">
                                @if ($project->amount) <p class="text-xs tabular-nums text-gray-500">₱{{ number_format($project->amount, 2) }}</p> @endif
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap">
                                <span class="rounded-full px-3 py-1 text-xs font-medium {{ $project->status->badgeClasses() }}">{{ $project->status->label() }}</span>
                            </td>
                            <td class="px-6 py-3 text-gray-700">
                                <span class="whitespace-nowrap tabular-nums">{{ __('Step :n of :t', ['n' => $project->progressStep(), 't' => count($project->steps())]) }}</span>
                                @if ($project->current_role)
                                    <span class="block text-xs text-gray-500">{{ __('Waiting on :office', ['office' => $project->current_role->shortLabel()]) }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-gray-700">{{ $project->submitted_at?->format('M j, Y') ?? '—' }}</td>
                            <td class="px-6 py-3 text-right">
                                <a href="{{ route('office.projects.show', $project) }}" class="inline-block rounded bg-lwua-dark px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-white shadow hover:brightness-110">{{ __('View') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-6 py-10 text-center text-gray-500">{{ __('No projects match these filters.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $projects->links() }}
    </div>
</x-app-layout>

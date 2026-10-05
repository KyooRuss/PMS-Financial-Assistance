<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <p class="text-sm font-medium text-lwua-blue">{{ $role->label() }}</p>
        <h1 class="text-2xl font-bold text-lwua-navy">{{ __('My Queue') }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ __('Requests waiting for your office to act, longest waiting first.') }}</p>

        @if ($role === \App\Enums\Role::SystemAdmin)
            <div class="mt-6 rounded-lg bg-white px-6 py-8 text-sm text-gray-500 shadow-md ring-1 ring-black/5">
                {{ __('Account management for water districts and LWUA offices will appear here.') }}
            </div>
        @else
            {{-- Tabs --}}
            <nav class="mt-6 flex gap-6 border-b border-gray-200 text-sm font-medium" aria-label="{{ __('Queue tabs') }}">
                @foreach (['queue' => __('Waiting for you').' ('.$queueCount.')', 'handled' => __('Handled by your office')] as $key => $label)
                    <a href="{{ route('dashboard', $key === 'queue' ? [] : ['tab' => $key]) }}"
                       @class(['-mb-px border-b-2 pb-3', 'border-lwua-blue text-lwua-blue' => $tab === $key, 'border-transparent text-gray-500 hover:text-gray-800' => $tab !== $key])
                       @if ($tab === $key) aria-current="page" @endif>
                        {{ $label }}
                    </a>
                @endforeach
            </nav>

            <div class="mt-4 overflow-x-auto rounded-lg bg-white shadow-md ring-1 ring-black/5">
                @if ($projects->isEmpty())
                    <p class="px-6 py-12 text-center text-sm text-gray-500">
                        {{ $tab === 'queue' ? __('Nothing is waiting for your office right now.') : __('Your office has not acted on any request yet.') }}
                    </p>
                @else
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-gray-200 text-xs font-semibold text-gray-700">
                            <tr>
                                <th scope="col" class="px-6 py-4 text-left">{{ __('Project ID') }}</th>
                                <th scope="col" class="px-6 py-4 text-left">{{ __('Water District') }}</th>
                                <th scope="col" class="px-6 py-4 text-left">{{ __('Project Type') }}</th>
                                <th scope="col" class="px-6 py-4 text-left">{{ __('Details') }}</th>
                                <th scope="col" class="px-6 py-4 text-left">{{ $tab === 'queue' ? __('Step') : __('Status') }}</th>
                                <th scope="col" class="px-6 py-4 text-left">{{ $tab === 'queue' ? __('Waiting Since') : __('Last Activity') }}</th>
                                <th scope="col" class="px-6 py-4"><span class="sr-only">{{ __('Actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($projects as $project)
                                @php($since = $project->last_activity_at ? \Illuminate\Support\Carbon::parse($project->last_activity_at) : $project->updated_at)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap font-bold text-lwua-navy">{{ $project->reference }}</td>
                                    <td class="px-6 py-4">
                                        <p class="text-gray-800">{{ $project->waterDistrict->name }}</p>
                                        <p class="text-xs text-gray-500">{{ __('Category :category', ['category' => $project->waterDistrict->category]) }}</p>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap uppercase text-gray-800">
                                        {{ $project->type->shortLabel() }}
                                        @if ($project->funding_purpose)
                                            <span class="block text-[11px] normal-case text-gray-500">{{ $project->funding_purpose->label() }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 max-w-xs">
                                        <p class="truncate text-gray-800" title="{{ $project->title }}">{{ $project->title }}</p>
                                        @if ($project->amount)
                                            <p class="text-xs text-gray-500">₱{{ number_format($project->amount, 2) }}</p>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-gray-800">
                                        @if ($tab === 'queue')
                                            {{ __('Step :n', ['n' => $project->current_step]) }} · {{ $project->currentStep()['action'] }}
                                            @if ($project->status === \App\Enums\ProjectStatus::Submitted && $project->was_returned)
                                                <span class="block text-xs text-amber-700">{{ __('Resubmitted after a return') }}</span>
                                            @endif
                                        @else
                                            <span class="rounded-full px-3 py-1 text-xs font-medium {{ $project->status->badgeClasses() }}">{{ $project->status->label() }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600" title="{{ $since->format('M j, Y g:i A') }}">
                                        {{ $since->diffForHumans() }}
                                    </td>
                                    <td class="px-6 py-4 text-right whitespace-nowrap">
                                        <a href="{{ route('office.projects.show', $project) }}"
                                           class="inline-block rounded bg-lwua-dark px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-white shadow hover:brightness-110">
                                            {{ $tab === 'queue' ? __('Review') : __('View') }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>

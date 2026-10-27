<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-10">

        {{-- Process Flow --}}
        <section id="process-flow">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h1 class="text-2xl font-bold text-lwua-navy">{{ __('Process Flow') }}</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ __('Track the progress of your loan request.') }}</p>
                </div>

                @if ($selected)
                    <p class="text-sm text-gray-600">
                        <span class="font-semibold text-lwua-navy">{{ $selected->reference }}</span>
                        · {{ $selected->type->label() }}
                        @if ($selected->funding_purpose)
                            ({{ $selected->funding_purpose->label() }})
                        @endif
                        · <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $selected->status->badgeClasses() }}">{{ $selected->status->label() }}</span>
                    </p>
                @endif
            </div>

            <div class="mt-4">
                @if ($selected)
                    <x-process-flow :project="$selected" />

                    {{-- Clicking an office on the stepper opens a modal with the files that office uploaded. --}}
                    @include('projects.partials.department-files-modal', ['project' => $selected])
                @else
                    <div class="rounded-lg bg-white px-6 py-8 text-center text-sm text-gray-500 shadow-md ring-1 ring-black/5">
                        {{ __('The process flow of your request will appear here once you request a project.') }}
                    </div>
                @endif
            </div>

        {{-- View Legend --}}
            <div class="mt-3 mr-2 text-right">
                <button type="button" 
                    x-data
                    x-on:click="$dispatch('open-modal', 'view-acronyms-modal')"
                    class="text-xs font-semibold tracking-wide text-lwua-sky underline hover:brightness-110">
                    {{ __('View Acronyms') }}
                </button>
            </div>  
        </section>

        {{-- Projects --}}
        <section>
            <h2 class="text-2xl font-bold text-lwua-navy">{{ __('Projects') }}</h2>
            <p class="mt-1 text-sm text-gray-500">{{ __('View your project details, status, date, and progress.') }}</p>

            <div class="mt-4 overflow-x-auto rounded-lg bg-white shadow-md ring-1 ring-black/5">
                @if ($projects->isEmpty())
                    <div class="px-6 py-12 text-center">
                        <p class="text-sm text-gray-500">{{ __('You have no projects yet.') }}</p>
                        <button type="button"
                                x-data
                                x-on:click="$dispatch('open-modal', 'select-project-type')"
                                class="mt-4 inline-flex items-center gap-1 rounded-md bg-lwua-sky px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow hover:brightness-110">
                            <span aria-hidden="true">+</span> {{ __('Request Project') }}
                        </button>
                    </div>
                @else
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-gray-200 text-xs font-semibold text-gray-700">
                            <tr>
                                <th scope="col" class="px-6 py-4 text-center">{{ __('Project ID') }}</th>
                                <th scope="col" class="px-6 py-4 text-center">{{ __('Project Type') }}</th>
                                <th scope="col" class="px-6 py-4 text-center">{{ __('Details') }}</th>
                                <th scope="col" class="px-6 py-4 text-center">{{ __('Status') }}</th>
                                <th scope="col" class="px-6 py-4 text-center">{{ __('Date Submitted') }}</th>
                                <th scope="col" class="px-6 py-4 text-center">{{ __('Progress') }}</th>
                                <th scope="col" class="px-6 py-4 text-center">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($projects as $project)
                                @php($url = route('dashboard', ['project' => $project->id]).'#process-flow')
                                @php($isSelected = $selected?->is($project))

                                <tr @class(['cursor-pointer hover:bg-gray-50', 'bg-blue-50/60' => $isSelected])
                                    x-data x-on:click="window.location = @js($url)">
                                    <td class="px-6 py-4 text-center whitespace-nowrap">
                                        <a href="{{ $url }}" class="font-bold text-lwua-navy hover:underline">{{ $project->reference }}</a>
                                    </td>
                                    <td class="px-6 py-4 text-center whitespace-nowrap uppercase text-gray-800">
                                        {{ $project->type->shortLabel() }}
                                        @if ($project->funding_purpose)
                                            <span class="block text-[11px] normal-case text-gray-500">{{ $project->funding_purpose->label() }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 max-w-xs text-center">
                                        @if ($project->amount)
                                            <p class="text-xs text-gray-500">₱{{ number_format($project->amount, 2) }}</p>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-center whitespace-nowrap">
                                        <span class="rounded-full px-3 py-1 text-xs font-medium {{ $project->status->badgeClasses() }}">{{ $project->status->label() }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-center whitespace-nowrap text-gray-800">
                                        {{ $project->submitted_at?->format('n/j/Y') ?? '—' }}
                                    </td>
                                    <td class="px-6 py-4 text-center whitespace-nowrap text-gray-800">
                                        {{ __('Step :current out of :total', ['current' => $project->progressStep(), 'total' => count($project->steps())]) }}
                                    </td>
                                    <td class="px-6 py-4 text-center whitespace-nowrap">
                                        @if ($project->isEditable())
                                            @if ($project->status === \App\Enums\ProjectStatus::Returned)
                                                <a href="{{ route('projects.edit', $project) }}" x-on:click.stop
                                                   class="inline-block rounded bg-amber-600 px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-white shadow hover:bg-amber-700">
                                                    {{ __('Resubmit') }}
                                                </a>
                                            @else
                                                <a href="{{ route('projects.edit', $project) }}" x-on:click.stop
                                                   class="inline-block rounded border border-lwua-dark bg-white px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-lwua-dark shadow hover:bg-gray-50">
                                                    {{ __('Continue Draft') }}
                                                </a>
                                            @endif
                                        @elseif ($project->status === \App\Enums\ProjectStatus::ForCompliance)
                                            <a href="{{ route('projects.compliance', $project) }}" x-on:click.stop
                                               class="inline-block rounded bg-amber-600 px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-white shadow hover:bg-amber-700">
                                                {{ __('Submit Documents') }}
                                            </a>
                                        @else
                                            @php($isCompleted = $project->status === \App\Enums\ProjectStatus::Completed)
                                            <a href="{{ route('dashboard', ['project' => $project->id, 'drawer' => $isCompleted ? 'files' : 'progress']) }}"
                                               x-on:click.stop
                                               @class([
                                                'inline-block rounded px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-white shadow',
                                                'bg-green-500 hover:bg-green-600' => $isCompleted,
                                                'bg-lwua-dark hover:brightness-110' => ! $isCompleted,
                                            ])>
                                                {{ $isCompleted ? __('View Project') : __('View Progress') }}
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </section>
    </div>
   
    @if ($drawer)
        @include('dashboard.partials.project-drawer')
    @endif
      @include('dashboard.partials.view-acronyms-modal')
    

</x-app-layout>

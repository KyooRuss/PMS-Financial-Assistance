@php
    $step = $project->currentStep();
    // Each step's buttons live in resources/views/office/actions/{step key}.blade.php.
    $panel = $canAct && view()->exists("office.actions.{$step['key']}") ? "office.actions.{$step['key']}" : null;

    // Reviews where the office flags checklist items to return: the form wraps the whole page.
    $flagging = match (true) {
        $panel && $step['key'] === 'completeness' => ['stage' => 'submission', 'action' => route('office.projects.return', $project)],
        $panel && $project->complianceReceived() => ['stage' => 'compliance', 'action' => route('office.projects.compliance.return', $project)],
        default => null,
    };
@endphp

<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        {{-- Heading --}}
        <div>
            @if (auth()->user()->hasRole(\App\Enums\Role::SystemAdmin))
                <a href="{{ route('admin.projects.index') }}" class="text-sm font-medium text-lwua-blue hover:underline">&larr; {{ __('All Projects') }}</a>
            @else
                <a href="{{ route('dashboard') }}" class="text-sm font-medium text-lwua-blue hover:underline">&larr; {{ __('My Queue') }}</a>
            @endif
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-lwua-navy">{{ $project->reference }} · {{ $project->title }}</h1>
                <span class="rounded-full px-3 py-1 text-xs font-medium {{ $project->status->badgeClasses() }}">{{ $project->status->label() }}</span>
            </div>
            <dl class="mt-3 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-gray-500">{{ __('Water District') }}</dt><dd class="font-medium text-gray-900">{{ $project->waterDistrict->name }} ({{ __('Category :c', ['c' => $project->waterDistrict->category]) }})</dd></div>
                <div><dt class="text-gray-500">{{ __('Project Type') }}</dt><dd class="font-medium text-gray-900">{{ $project->type->label() }} @if ($project->funding_purpose) ({{ $project->funding_purpose->label() }}) @endif · {{ $project->type->annex() }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Amount Requested') }}</dt><dd class="font-medium text-gray-900 tabular-nums">{{ $project->amount ? '₱'.number_format($project->amount, 2) : '—' }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Submitted') }}</dt><dd class="font-medium text-gray-900">{{ $project->submitted_at?->format('M j, Y') ?? '—' }} · {{ $project->user->full_name }}</dd></div>
            </dl>
            @if ($project->description)
                <p class="mt-3 max-w-3xl text-sm text-gray-600">{{ $project->description }}</p>
            @endif
        </div>

        <x-process-flow :project="$project" />

        @if ($errors->any())
            <div role="alert" class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">
                {{ __('Please fix the highlighted items.') }}
                @error('items') <span class="block">{{ $message }}</span> @enderror
            </div>
        @endif

        @if ($flagging)
            <form method="POST" action="{{ $flagging['action'] }}" id="action-form">
                @csrf
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Documents --}}
            <div class="space-y-6 lg:col-span-2">
                @include('office.projects.partials.assessment-summary')

                @if ($project->compliance_requested_at)
                    <section class="rounded-lg bg-white p-6 shadow-md ring-1 ring-black/5">
                        <h2 class="text-lg font-semibold text-lwua-navy">{{ __('Post-Evaluation Documents') }}</h2>
                        <p class="mb-3 text-xs text-gray-500">
                            {{ \App\Enums\RequirementStage::PostEvaluation->label() }}
                            @if (! $project->type->isEmergency())
                                · {{ $project->cfp_concurred_at
                                    ? __('Concurred with the cash flow projections on :date', ['date' => $project->cfp_concurred_at->format('M j, Y')])
                                    : __('Cash flow projections not yet concurred') }}
                            @endif
                        </p>
                        @include('projects.partials.checklist', ['requirements' => $project->complianceRequirements(), 'flaggable' => ($flagging['stage'] ?? null) === 'compliance'])
                    </section>
                @endif

                <section class="rounded-lg bg-white p-6 shadow-md ring-1 ring-black/5">
                    <h2 class="text-lg font-semibold text-lwua-navy">{{ __('Submitted Requirements') }}</h2>
                    <p class="mb-3 text-xs text-gray-500">{{ \App\Enums\RequirementStage::PreEvaluation->label() }}</p>
                    @include('projects.partials.checklist', ['requirements' => $submission, 'flaggable' => ($flagging['stage'] ?? null) === 'submission'])
                </section>

                <section class="rounded-lg bg-white p-6 shadow-md ring-1 ring-black/5">
                    <h2 class="text-lg font-semibold text-lwua-navy">{{ __('LWUA Documents') }}</h2>
                    <p class="mb-3 text-xs text-gray-500">{{ \App\Enums\RequirementStage::LwuaUnit->label() }}</p>
                    @include('projects.partials.lwua-documents', ['requirements' => $lwuaDocuments])
                </section>
            </div>

            {{-- Action panel + timeline --}}
            <div class="space-y-6">
                <section class="rounded-lg bg-white p-6 shadow-md ring-1 ring-black/5">
                    <h2 class="text-lg font-semibold text-lwua-navy">{{ __('Action') }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('Step :n of :total', ['n' => $project->progressStep(), 'total' => count($project->steps())]) }} · {{ $step['unit'] }} – {{ $step['action'] }}</p>

                    @if ($panel)
                        @include($panel)
                    @elseif ($canAct)
                        <p class="mt-3 rounded-md bg-gray-50 px-4 py-3 text-sm text-gray-600">{{ __('It is your office\'s turn. The buttons for this step will be added in a later build.') }}</p>
                    @else
                        <p class="mt-3 rounded-md bg-gray-50 px-4 py-3 text-sm text-gray-600">
                            @if ($project->waitingOn())
                                {{ __('Waiting on :office. You can view this request, but only that office can act on it now.', ['office' => $project->waitingOn()->label()]) }}
                            @else
                                {{ __('This request is closed: :status.', ['status' => $project->status->label()]) }}
                            @endif
                        </p>
                    @endif
                </section>

                <section class="rounded-lg bg-white p-6 shadow-md ring-1 ring-black/5">
                    <h2 class="text-lg font-semibold text-lwua-navy">{{ __('Timeline') }}</h2>
                    @include('projects.partials.timeline')
                </section>
            </div>
        </div>

        @if ($flagging)
            </form>
        @endif
    </div>
</x-app-layout>

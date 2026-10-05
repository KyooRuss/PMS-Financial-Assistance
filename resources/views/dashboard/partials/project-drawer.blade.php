{{-- View Project drawer (Figma: "USER – VIEW UPLOADED FILES"). Opened with ?project={id}&drawer=files|progress. --}}
@php
    $project = $selected;
    $step = $project->currentStep();
@endphp

<div x-data="{
        open: true,
        tab: @js($drawer),
        close() {
            this.open = false;
            const url = new URL(window.location);
            url.searchParams.delete('drawer');
            history.replaceState(null, '', url);
        },
     }"
     x-show="open"
     x-init="$watch('open', value => document.body.classList.toggle('overflow-hidden', value)); document.body.classList.add('overflow-hidden')"
     x-on:keydown.escape.window="close()"
     class="fixed inset-0 z-40">

    <div class="absolute inset-0 bg-gray-900/50" x-on:click="close()" aria-hidden="true"></div>

    <aside role="dialog" aria-modal="true" aria-labelledby="drawer-title"
           class="absolute inset-y-0 right-0 flex w-full max-w-xl flex-col bg-white shadow-2xl">

        {{-- Header --}}
        <div class="border-b border-gray-200 px-6 pt-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <h2 id="drawer-title" class="text-lg font-bold text-gray-900">{{ $project->reference }}</h2>
                        <span class="rounded-full px-3 py-0.5 text-xs font-medium {{ $project->status->badgeClasses() }}">{{ $project->status->label() }}</span>
                    </div>
                    <p class="mt-1 text-sm italic text-gray-700">
                        {{ $project->type->annex() }}: {{ $project->type->label() }}
                        @if ($project->funding_purpose) ({{ $project->funding_purpose->label() }}) @endif
                    </p>
                    <p class="mt-1 text-sm text-gray-500">{{ $project->title }}</p>
                </div>
                <button type="button" x-on:click="close()" class="rounded p-1 text-gray-700 hover:bg-gray-100" aria-label="{{ __('Close') }}">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            @if ($project->status === \App\Enums\ProjectStatus::Returned)
                <div class="mt-4 flex items-center justify-between gap-3 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200">
                    <span>{{ __('WSUDD returned this request. Fix the flagged items and resubmit.') }}</span>
                    <a href="{{ route('projects.edit', $project) }}" class="shrink-0 rounded bg-amber-600 px-3 py-1.5 text-xs font-semibold uppercase text-white hover:bg-amber-700">{{ __('Fix & Resubmit') }}</a>
                </div>
            @endif

            @if ($project->status === \App\Enums\ProjectStatus::ForCompliance)
                <div class="mt-4 flex items-center justify-between gap-3 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200">
                    <span>{{ __('LWRD is waiting for your post-evaluation documents.') }}</span>
                    <a href="{{ route('projects.compliance', $project) }}" class="shrink-0 rounded bg-amber-600 px-3 py-1.5 text-xs font-semibold uppercase text-white hover:bg-amber-700">{{ __('Submit Documents') }}</a>
                </div>
            @endif

            {{-- Tabs --}}
            <div class="mt-4 flex gap-6 text-sm font-medium" role="tablist">
                @foreach (['files' => __('Uploaded Files'), 'progress' => __('Progress')] as $key => $label)
                    <button type="button" role="tab" x-on:click="tab = @js($key)"
                            :aria-selected="tab === @js($key)"
                            :class="tab === @js($key) ? 'border-lwua-blue text-lwua-blue' : 'border-transparent text-gray-500 hover:text-gray-800'"
                            class="-mb-px border-b-2 pb-3">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="flex-1 overflow-y-auto bg-gray-50/60 px-6 py-5">

            {{-- Files tab --}}
            <div x-show="tab === 'files'" x-cloak role="tabpanel" class="space-y-6">
                <div>
                    @if ($project->status === \App\Enums\ProjectStatus::Draft)
                        <div class="mb-3 text-right">
                            <a href="{{ route('projects.edit', $project) }}" class="rounded border border-lwua-dark px-3 py-1.5 text-xs font-semibold uppercase text-lwua-dark hover:bg-white">{{ __('Continue Draft') }}</a>
                        </div>
                    @endif
                    @include('projects.partials.checklist', ['requirements' => $checklists[\App\Enums\RequirementStage::PreEvaluation->value]])
                </div>

                @if ($project->compliance_requested_at)
                    <div>
                        <h3 class="mb-3 text-sm font-semibold text-gray-900">{{ __('Post-Evaluation Documents') }}</h3>
                        @include('projects.partials.checklist', ['requirements' => $project->complianceRequirements()])
                    </div>
                @endif

                <div>
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('LWUA Documents') }}</h3>
                    <p class="mb-3 text-xs text-gray-500">{{ __('Added by the LWUA offices as your request moves forward.') }}</p>
                    @include('projects.partials.lwua-documents', ['requirements' => $checklists[\App\Enums\RequirementStage::LwuaUnit->value]])
                </div>
            </div>

            {{-- Progress tab --}}
            <div x-show="tab === 'progress'" x-cloak role="tabpanel" class="space-y-6">
                <dl class="space-y-3 text-sm">
                    <div class="flex gap-3 rounded-md bg-gray-100 px-4 py-3">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div><dt class="inline font-semibold">{{ __('Status:') }}</dt> <dd class="inline">{{ $project->status->label() }}</dd></div>
                    </div>
                    <div class="flex gap-3 rounded-md bg-gray-100 px-4 py-3">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        <div>
                            <dt class="inline font-semibold">{{ __('Current Step:') }}</dt>
                            <dd class="inline">{{ __('Step :current of :total', ['current' => $project->progressStep(), 'total' => count($project->steps())]) }} · {{ $step['unit'] }} – {{ $step['action'] }}</dd>
                        </div>
                    </div>
                    <div class="flex gap-3 rounded-md bg-gray-100 px-4 py-3">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <div><dt class="inline font-semibold">{{ __('Details:') }}</dt> <dd class="inline">{{ $project->statusMessage() }}</dd></div>
                    </div>
                </dl>

                <div>
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('Timeline') }}</h3>
                    @include('projects.partials.timeline')
                </div>
            </div>
        </div>
    </aside>
</div>

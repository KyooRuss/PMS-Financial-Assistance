{{--
    Files each department uploaded, shown when an office is clicked on the stepper.
    Expects: $project. Needs a parent Alpine scope with `selectedOffice` (a role value, or null).
    Internal office files are hidden from the water district.
--}}
@php
    $steps = $project->steps();
    $filesByDepartment = $project->filesByDepartment(includeInternal: auth()->user()->role->isLwuaOffice());

    // One panel per department, in the order it first appears on the stepper. The water district's steps are not clickable, so it has no panel.
    $departments = collect($steps)
        ->unique(fn (array $step) => \App\Support\ProcessFlow::roleFor($step['unit'])->value)
        ->reject(fn (array $step) => \App\Support\ProcessFlow::roleFor($step['unit']) === \App\Enums\Role::Lwd);
@endphp

@foreach ($departments as $department)
    @php
        $role = \App\Support\ProcessFlow::roleFor($department['unit']);

        // Office files are grouped by the step they were uploaded on; water district files by checklist.
        $groups = $filesByDepartment->get($role->value, collect())
            ->sortBy(fn ($file) => $file->log
                ? $file->log->step
                : ($file->requirement?->stage === \App\Enums\RequirementStage::PostEvaluation ? 1 : 0))
            ->groupBy(fn ($file) => match (true) {
                $file->log !== null => __('Step :n · :action', ['n' => $file->log->step, 'action' => $steps[$file->log->step - 1]['action'] ?? '']),
                $file->requirement?->stage === \App\Enums\RequirementStage::PostEvaluation => __('Post-Evaluation Documents'),
                default => __('Submitted Requirements'),
            });
    @endphp

    <div x-show="selectedOffice === @js($role->value)" x-cloak class="mt-3">
        <p class="text-sm text-gray-900">
            <span class="mr-1 rounded bg-blue-50 px-1.5 py-0.5 text-[11px] font-semibold text-lwua-navy">{{ $department['unit'] }}</span>
            <span class="text-gray-500">{{ $role->label() }}</span>
        </p>

        @if ($groups->isEmpty())
            <p class="mt-3 text-sm text-gray-500">{{ __('No files uploaded by :office yet.', ['office' => $department['unit']]) }}</p>
        @else
            <div class="mt-3 space-y-4">
                @foreach ($groups as $heading => $files)
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $heading }}</h3>
                        <ul class="mt-1.5 divide-y divide-gray-100 rounded-md bg-white ring-1 ring-gray-100">
                            @foreach ($files as $file)
                                <li class="px-3 py-2 text-xs">
                                    <a href="{{ route('documents.show', $file) }}" target="_blank" rel="noopener" class="inline-flex items-start gap-1 font-medium text-lwua-blue underline">
                                        <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                        <span class="break-all">{{ $file->original_name }}</span>
                                    </a>
                                    @if ($file->requirement)
                                        <p class="mt-0.5 text-gray-600">
                                            @if ($file->requirement->stage->isSubmittedByWaterDistrict())
                                                {{ __('Item :number', ['number' => $file->requirement->number]) }} –
                                            @endif
                                            {{ $file->requirement->description }}
                                        </p>
                                    @endif
                                    <p class="mt-0.5 text-gray-400">{{ $file->created_at->format('M j, Y · g:i A') }}</p>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endforeach

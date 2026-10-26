{{-- ES and LWRD rapid assessments side by side, for CPIC and everyone after. Regular loans only. --}}
@php
    $powLog = $project->logsForStep('pow_assessment')->first();
    $viabilityLog = $project->logsForStep('viability_assessment')->first();
@endphp

@if (Auth::user()->hasRole(\App\Enums\Role::Es, \App\Enums\Role::Lwrd, \App\Enums\Role::Cpic, \App\Enums\Role::SystemAdmin))
    <section class="rounded-lg bg-white p-6 shadow-md ring-1 ring-black/5">
        <h2 class="text-lg font-semibold text-lwua-navy">{{ __('Rapid Assessments') }}</h2>
        <p class="text-xs text-gray-500">{{ __('For CPIC\'s funding and prioritization decision.') }}</p>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            @foreach ([[__('ES · WD-POW'), $powLog], [__('LWRD · Financial viability'), $viabilityLog]] as [$label, $log])
                <div class="rounded-md bg-gray-50 p-4 ring-1 ring-gray-100">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-900">{{ $label }}</h3>
                        @if ($log && $log === $viabilityLog && $project->is_viable !== null)
                            <span @class([
                                'shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                'bg-green-100 text-green-700' => $project->is_viable,
                                'bg-red-100 text-red-700' => ! $project->is_viable,
                            ])>{{ $project->is_viable ? __('Financially Viable') : __('Not Viable') }}</span>
                        @endif
                    </div>

                    @if ($log)
                        <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $log->remarks }}</p>
                        <p class="mt-2 text-xs text-gray-500">{{ $log->created_at->format('M j, Y') }} · {{ $log->user?->full_name }}</p>
                        @foreach ($log->documents as $file)
                            <a href="{{ route('documents.show', $file) }}" target="_blank" rel="noopener" class="mt-1 block truncate text-xs text-lwua-blue underline">{{ $file->original_name }}</a>
                        @endforeach
                    @else
                        <p class="mt-2 text-sm text-gray-400">{{ __('Not done yet.') }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
@endif

{{-- A list of timeline entries across projects. Expects $logs (with project.waterDistrict and user). --}}
@if ($logs->isEmpty())
    <p class="mt-4 text-sm text-gray-500">{{ __('No activity found.') }}</p>
@else
    <ul class="mt-3 divide-y divide-gray-100">
        @foreach ($logs as $log)
            <li class="flex flex-col gap-1 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-6">
                <div class="min-w-0">
                    <p class="text-sm text-gray-900">
                        <span class="mr-1 rounded bg-blue-50 px-1.5 py-0.5 text-[11px] font-semibold text-lwua-navy">{{ $log->office }}</span>
                        {{ $log->action }}
                    </p>
                    <p class="mt-0.5 text-xs text-gray-500">
                        <a href="{{ route('office.projects.show', $log->project) }}" class="font-medium text-lwua-blue hover:underline">{{ $log->project->reference }}</a>
                        · {{ $log->project->title }} · {{ $log->project->waterDistrict->name }}
                    </p>
                </div>
                <p class="shrink-0 text-xs text-gray-500 sm:text-right">
                    {{ $log->user?->full_name ?? __('System') }}<br>
                    <span title="{{ $log->created_at->format('M j, Y g:i A') }}">{{ $log->created_at->format('M j, Y · g:i A') }}</span>
                </p>
            </li>
        @endforeach
    </ul>
@endif

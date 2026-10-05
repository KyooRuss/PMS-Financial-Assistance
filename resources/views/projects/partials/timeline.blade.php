{{-- The project's history, newest first. Expects: $project (logs.user and logs.returnedItems.requirement loaded). --}}
@if ($project->logs->isEmpty())
    <p class="mt-2 text-sm text-gray-500">{{ __('No activity yet. Actions appear here once the request is submitted.') }}</p>
@else
    <ol class="relative mt-3 space-y-5 border-l-2 border-gray-200 pl-5">
        @foreach ($project->logs as $log)
            <li class="relative">
                <span @class([
                    'absolute -left-[27px] top-1 h-3 w-3 rounded-full ring-4 ring-white',
                    'bg-lwua-blue' => $loop->first,
                    'bg-gray-300' => ! $loop->first,
                ])></span>
                <p class="text-sm text-gray-900">
                    <span class="mr-1 rounded bg-blue-50 px-1.5 py-0.5 text-[11px] font-semibold text-lwua-navy">{{ $log->office }}</span>
                    {{ $log->action }}
                </p>
                <p class="mt-0.5 text-xs text-gray-500">
                    {{ $log->created_at->format('M j, Y · g:i A') }}
                    @if ($log->user) · {{ $log->user->full_name }} @endif
                </p>
                {{-- Files uploaded with the action; internal office attachments are hidden from the water district. --}}
                @php
                    $attachments = auth()->user()->role->isLwuaOffice() ? $log->documents : $log->documents->reject->isInternal();
                @endphp
                @foreach ($attachments as $file)
                    <a href="{{ route('documents.show', $file) }}" target="_blank" rel="noopener" class="mt-1 inline-flex items-center gap-1 text-xs text-lwua-blue underline">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                        {{ $file->original_name }}
                    </a>
                @endforeach

                @if ($log->remarks || $log->returnedItems->isNotEmpty())
                    <div class="mt-1 space-y-1 rounded bg-amber-50 px-3 py-2 text-xs text-amber-900">
                        @if ($log->remarks)
                            <p>{{ $log->remarks }}</p>
                        @endif
                        @if ($log->returnedItems->isNotEmpty())
                            <ul class="list-disc space-y-0.5 pl-4">
                                @foreach ($log->returnedItems as $returned)
                                    <li><span class="font-semibold">{{ __('Item :number', ['number' => $returned->requirement->number]) }}:</span> {{ $returned->remarks }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif
            </li>
        @endforeach
    </ol>
@endif

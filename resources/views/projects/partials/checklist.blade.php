{{--
    The water district's submission checklist with each item's state.
    Expects: $project (documents + returnedItems loaded), $requirements (pre-evaluation checklist).
    Optional: $flaggable = true adds "flag for return" controls for the reviewing office (inside its form).
--}}
@php
    $flaggable ??= false;
    $filesByRequirement = $project->documents->groupBy('requirement_id');
    $returns = $project->returnedItems->whereNull('resolved_at')->whereIn('requirement_id', $requirements->pluck('id'))->keyBy('requirement_id');
    $items = $requirements->map(function ($requirement) use ($project, $filesByRequirement, $returns) {
        $files = $filesByRequirement->get($requirement->id, collect());

        return (object) [
            'requirement' => $requirement,
            'files' => $files,
            'return' => $returns->get($requirement->id),
            'state' => $project->requirementState($requirement, $files, $returns->get($requirement->id)),
        ];
    });
    $required = $items->whereIn('state', ['uploaded', 'missing', 'returned']);
@endphp

<p class="flex items-center gap-2 text-sm text-gray-700">
    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/></svg>
    {{ __(':done of :total required items uploaded', ['done' => $required->where('state', 'uploaded')->count(), 'total' => $required->count()]) }}
    @if ($returns->isNotEmpty())
        <span class="text-amber-700">· {{ trans_choice(':count item returned|:count items returned', $returns->count()) }}</span>
    @endif
</p>

<ol class="mt-3 space-y-3">
    @foreach ($items as $item)
        @php([$requirement, $files, $state] = [$item->requirement, $item->files, $item->state])

        <li @if ($flaggable && $state !== 'not_applicable') x-data="{ flag: @js((bool) old("items.{$requirement->id}.flag")) }" @endif
            @class([
                'rounded-md px-4 py-3',
                'bg-gray-100' => ! in_array($state, ['not_applicable', 'returned']),
                'bg-amber-50 ring-1 ring-amber-200' => $state === 'returned',
                'bg-white text-gray-400 ring-1 ring-gray-100' => $state === 'not_applicable',
            ])>
            <div class="flex items-start justify-between gap-3">
                <p @class(['text-sm font-semibold', 'text-gray-900' => $state !== 'not_applicable'])>
                    {{ $requirement->number }}. {{ $requirement->description }}
                </p>
                <span @class([
                    'shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium',
                    'bg-green-100 text-green-700' => $state === 'uploaded',
                    'bg-red-100 text-red-700' => $state === 'missing',
                    'bg-amber-100 text-amber-800' => $state === 'returned',
                    'bg-gray-200 text-gray-600' => $state === 'optional',
                    'bg-gray-100 text-gray-500' => $state === 'not_applicable',
                ])>
                    {{ match ($state) {
                        'uploaded' => __('Uploaded'),
                        'missing' => __('Missing'),
                        'returned' => __('Returned'),
                        'optional' => __('Not provided · optional'),
                        'not_applicable' => __('Not applicable (:purpose only)', ['purpose' => $requirement->funding_purpose?->label()]),
                    } }}
                </span>
            </div>

            @if ($item->return)
                <p class="mt-2 rounded bg-white px-3 py-2 text-xs text-amber-900 ring-1 ring-amber-200">
                    <span class="font-semibold">{{ __('Remarks:') }}</span> {{ $item->return->remarks }}
                </p>
            @endif

            @if ($files->isNotEmpty())
                <ul class="mt-2 space-y-1">
                    @foreach ($files as $file)
                        <li class="flex items-center justify-between gap-4 text-xs">
                            <span class="truncate text-gray-700">{{ $file->original_name }} <span class="text-gray-400">· {{ \Illuminate\Support\Number::fileSize($file->size) }} · {{ $file->created_at->format('M j, Y') }}</span></span>
                            <a href="{{ route('documents.show', $file) }}" target="_blank" rel="noopener" class="shrink-0 font-medium text-lwua-blue underline hover:no-underline">{{ __('View') }}</a>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($flaggable && $state !== 'not_applicable')
                <div class="mt-3 border-t border-gray-200 pt-2">
                    <label class="inline-flex items-center gap-2 text-xs font-medium text-amber-800">
                        <input type="checkbox" name="items[{{ $requirement->id }}][flag]" value="1" x-model="flag"
                               class="rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                        {{ __('Flag for return') }}
                    </label>
                    <div x-show="flag" x-cloak class="mt-2">
                        <label for="item-remarks-{{ $requirement->id }}" class="sr-only">{{ __('What needs to be fixed') }}</label>
                        <textarea id="item-remarks-{{ $requirement->id }}" name="items[{{ $requirement->id }}][remarks]" rows="2" maxlength="1000"
                                  placeholder="{{ __('What needs to be fixed, e.g. "Certification is not signed."') }}"
                                  :disabled="! flag"
                                  class="block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-amber-500 focus:ring-amber-500">{{ old("items.{$requirement->id}.remarks") }}</textarea>
                        <x-input-error class="mt-1" :messages="$errors->get('items.'.$requirement->id.'.remarks')" />
                    </div>
                </div>
            @endif
        </li>
    @endforeach
</ol>

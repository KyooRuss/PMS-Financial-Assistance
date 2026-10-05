{{--
    Documents LWUA's own offices add along the way ("Concerned LWUA Units" in the annex).
    Expects: $project (documents loaded), $requirements (LWUA unit checklist).
--}}
@php($filesByRequirement = $project->documents->groupBy('requirement_id'))

<ul class="divide-y divide-gray-100 rounded-md bg-white ring-1 ring-gray-100">
    @foreach ($requirements as $requirement)
        @php($files = $filesByRequirement->get($requirement->id, collect()))
        <li class="flex items-start justify-between gap-3 px-4 py-2.5 text-xs">
            <div>
                <p class="text-gray-800">{{ $requirement->description }}</p>
                <p class="text-gray-400">{{ $requirement->responsible_unit }}</p>
                @foreach ($files as $file)
                    <a href="{{ route('documents.show', $file) }}" target="_blank" rel="noopener" class="block text-lwua-blue underline">{{ $file->original_name }}</a>
                @endforeach
            </div>
            <span class="shrink-0 {{ $files->isNotEmpty() ? 'text-green-700' : 'text-gray-400' }}">
                {{ $files->isNotEmpty() ? __('Available') : __('Pending') }}
            </span>
        </li>
    @endforeach
</ul>

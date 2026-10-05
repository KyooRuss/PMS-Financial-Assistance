<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        @include('admin.partials.heading', ['title' => __('Submitted Files'), 'subtitle' => __('Every file uploaded in PMS: water district submissions, LWUA documents and letters, and internal office files.')])

        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="q" class="block text-xs font-medium text-gray-600">{{ __('Search') }}</label>
                <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="{{ __('File name or project title') }}"
                       class="mt-1 w-64 rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">
            </div>
            <div>
                <label for="kind" class="block text-xs font-medium text-gray-600">{{ __('Kind') }}</label>
                <select id="kind" name="kind" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">
                    <option value="">{{ __('All files') }}</option>
                    @foreach (\App\Http\Controllers\Admin\DocumentController::KINDS as $value => $label)
                        <option value="{{ $value }}" @selected($kind === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="district" class="block text-xs font-medium text-gray-600">{{ __('Water district') }}</label>
                <select id="district" name="district" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($districts as $district)
                        <option value="{{ $district->id }}" @selected((int) request('district') === $district->id)>{{ $district->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-md bg-lwua-dark px-4 py-2 text-sm font-semibold text-white shadow hover:brightness-110">{{ __('Filter') }}</button>
            @if (request()->hasAny(['q', 'kind', 'district']))
                <a href="{{ route('admin.documents.index') }}" class="py-2 text-sm text-gray-500 hover:text-gray-800">{{ __('Clear') }}</a>
            @endif
        </form>

        <div class="overflow-x-auto rounded-lg bg-white shadow-md ring-1 ring-black/5">
            <table class="min-w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs font-semibold text-gray-600">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left">{{ __('File') }}</th>
                        <th scope="col" class="px-6 py-3 text-left">{{ __('For') }}</th>
                        <th scope="col" class="px-6 py-3 text-left">{{ __('Project') }}</th>
                        <th scope="col" class="px-6 py-3 text-left">{{ __('Uploaded by') }}</th>
                        <th scope="col" class="px-6 py-3 text-left">{{ __('Date') }}</th>
                        <th scope="col" class="px-6 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($documents as $document)
                        <tr class="hover:bg-gray-50">
                            <td class="max-w-xs px-6 py-3">
                                <p class="truncate text-gray-900" title="{{ $document->original_name }}">{{ $document->original_name }}</p>
                                <p class="text-xs tabular-nums text-gray-500">
                                    {{ \Illuminate\Support\Number::fileSize($document->size) }}
                                    @if ($document->isInternal()) · <span class="font-medium text-amber-700">{{ __('Internal') }}</span> @endif
                                </p>
                            </td>
                            <td class="max-w-xs px-6 py-3 text-gray-700">
                                @if ($document->requirement)
                                    <p class="truncate" title="{{ $document->requirement->description }}">
                                        @if ($document->requirement->stage !== \App\Enums\RequirementStage::LwuaUnit) {{ __('Item :n', ['n' => $document->requirement->number]) }} · @endif
                                        {{ $document->requirement->description }}
                                    </p>
                                    <p class="text-xs text-gray-500">{{ $document->requirement->stage->label() }}</p>
                                @else
                                    <p class="text-gray-500">{{ __('Attachment to an office action') }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-3">
                                <a href="{{ route('office.projects.show', $document->project) }}" class="font-medium text-lwua-blue hover:underline">{{ $document->project->reference }}</a>
                                <p class="text-xs text-gray-500">{{ $document->project->waterDistrict->name }}</p>
                            </td>
                            <td class="px-6 py-3 text-gray-700">
                                {{ $document->uploader?->full_name }}
                                <p class="text-xs text-gray-500">{{ $document->uploader?->role->shortLabel() }}</p>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-gray-700">{{ $document->created_at->format('M j, Y') }}</td>
                            <td class="px-6 py-3 text-right">
                                <a href="{{ route('documents.show', $document) }}" target="_blank" rel="noopener" class="font-medium text-lwua-blue underline hover:no-underline">{{ __('View') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-10 text-center text-gray-500">{{ __('No files match these filters.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $documents->links() }}
    </div>
</x-app-layout>

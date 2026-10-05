{{-- Page 8 – the water district uploads its post-evaluation documents for LWRD. --}}
@php
    $removed = collect(old('remove_documents', []))->map(fn ($id) => (int) $id);
    $documentsByRequirement = $project->documents->groupBy('requirement_id');
    $returns = $project->returnedItems->keyBy('requirement_id'); // open returns only (see edit())
    $isRegular = ! $project->type->isEmergency();
    $maxUploads = (int) ini_get('max_file_uploads');
@endphp

<x-app-layout>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-wrap items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold text-lwua-navy">{{ __('Post-Evaluation Documents') }}</h1>
                <p class="mt-1 text-sm text-gray-500">
                    <span class="font-semibold text-lwua-navy">{{ $project->reference }}</span> · {{ $project->title }} · {{ $project->type->label() }}
                </p>
            </div>
            <span class="rounded-full px-3 py-1 text-xs font-medium {{ $project->status->badgeClasses() }}">{{ $project->status->label() }}</span>
        </div>

        {{-- LWRD's letter, or its return remarks --}}
        @if ($letter)
            <div class="mt-6 rounded-md bg-blue-50 px-5 py-4 text-sm text-lwua-navy ring-1 ring-blue-200">
                <p class="font-semibold">{{ __('From LWRD · :date', ['date' => $letter->created_at->format('M j, Y')]) }}</p>
                <p class="mt-1">{{ $letter->action }}</p>
                @if ($letter->remarks)
                    <p class="mt-2 whitespace-pre-line rounded bg-white px-3 py-2 text-gray-800 ring-1 ring-blue-100">{{ $letter->remarks }}</p>
                @endif
                @foreach ($letter->documents->reject->isInternal() as $file)
                    <a href="{{ route('documents.show', $file) }}" target="_blank" rel="noopener" class="mt-2 inline-block font-medium underline">{{ __('Read the letter: :name', ['name' => $file->original_name]) }}</a>
                @endforeach
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" class="mt-6 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">
                {{ __('Please fix the highlighted items below.') }}
                @error('documents') <span class="block">{{ $message }}</span> @enderror
            </div>
        @endif

        <form method="POST" action="{{ route('projects.compliance.update', $project) }}" enctype="multipart/form-data"
              x-data="{
                  fileCount: 0,
                  maxUploads: {{ $maxUploads }},
                  countFiles() { this.fileCount = [...$el.querySelectorAll('input[type=file]')].reduce((total, input) => total + input.files.length, 0) },
              }"
              x-on:change="countFiles()"
              class="mt-6 space-y-6">
            @csrf
            @method('PUT')

            <section class="rounded-lg bg-white shadow-md ring-1 ring-black/5">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-lg font-semibold text-lwua-navy">{{ \App\Enums\RequirementStage::PostEvaluation->label() }}</h2>
                    <p class="text-xs text-gray-500">{{ __('PDF only, up to 10 MB per file. Select all files for an item at once.') }}</p>
                </div>

                <ol class="divide-y divide-gray-100">
                    @foreach ($requirements as $requirement)
                        @php
                            $key = "documents.{$requirement->id}";
                            $existing = $documentsByRequirement->get($requirement->id, collect());
                            $return = $returns->get($requirement->id);
                            $hasError = $errors->has($key) || $errors->has($key.'.*');
                        @endphp

                        <li x-data="{ chosen: [] }" @class(['px-6 py-4', 'bg-red-50/60' => $hasError, 'bg-amber-50' => $return && ! $hasError])>
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="text-sm text-gray-800">
                                    <p>
                                        <span class="font-semibold">{{ $requirement->number }}.</span>
                                        {{ $requirement->description }}
                                        @if ($requirement->is_optional)
                                            <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600">{{ __('If applicable') }}</span>
                                        @endif
                                        @if ($return)
                                            <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-medium text-amber-800">{{ __('Returned') }}</span>
                                        @endif
                                    </p>
                                    @if ($return)
                                        <p class="mt-1 text-amber-900"><span class="font-semibold">{{ __('To fix:') }}</span> {{ $return->remarks }}</p>
                                    @endif
                                </div>

                                <label class="inline-flex shrink-0 cursor-pointer items-center gap-2 self-start rounded border border-gray-300 bg-white px-4 py-2 text-xs text-gray-600 shadow-sm hover:bg-gray-50 focus-within:ring-2 focus-within:ring-lwua-blue">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v12m0-12l-4 4m4-4l4 4"/></svg>
                                    {{ __('Upload File') }}
                                    <input type="file" name="documents[{{ $requirement->id }}][]" multiple accept="application/pdf,.pdf" class="sr-only"
                                           x-on:change="chosen = [...$event.target.files].map(file => file.name)">
                                </label>
                            </div>

                            @if ($existing->isNotEmpty())
                                <ul class="mt-3 space-y-1">
                                    @foreach ($existing as $document)
                                        <li x-data="{ remove: @js($removed->contains($document->id)) }" class="flex items-center justify-between gap-4 rounded bg-gray-50 px-3 py-1.5 text-xs">
                                            <a href="{{ route('documents.show', $document) }}" target="_blank" class="truncate text-lwua-blue hover:underline" :class="remove && 'line-through text-gray-400'">
                                                {{ $document->original_name }}
                                            </a>
                                            <label class="inline-flex shrink-0 items-center gap-1 text-red-600">
                                                <input type="checkbox" name="remove_documents[]" value="{{ $document->id }}" x-model="remove" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                                                {{ __('Remove') }}
                                            </label>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <ul x-show="chosen.length" class="mt-2 space-y-1 text-xs text-gray-600">
                                <template x-for="name in chosen" :key="name">
                                    <li class="flex items-center gap-2"><span class="rounded bg-green-50 px-1.5 text-green-700">{{ __('New') }}</span> <span x-text="name"></span></li>
                                </template>
                            </ul>

                            <x-input-error class="mt-2" :messages="array_merge($errors->get($key), $errors->get($key.'.*'))" />
                        </li>
                    @endforeach
                </ol>
            </section>

            @if ($isRegular)
                <section class="rounded-lg bg-white px-6 py-4 shadow-md ring-1 ring-black/5">
                    <label class="flex items-start gap-3 text-sm text-gray-800">
                        <input type="checkbox" name="cfp_concurrence" value="1" @checked(old('cfp_concurrence', $project->cfp_concurred_at !== null))
                               class="mt-0.5 rounded border-gray-300 text-lwua-blue focus:ring-lwua-blue">
                        <span>
                            <span class="font-semibold">{{ __('We concur with the cash flow projections (CFP).') }}</span>
                            <span class="block text-xs text-gray-500">{{ __('The water district agrees with the cash flow projections LWRD used in its evaluation.') }}</span>
                        </span>
                    </label>
                    <x-input-error class="mt-2" :messages="$errors->get('cfp_concurrence')" />
                </section>
            @endif

            <div class="flex flex-col-reverse items-stretch gap-3 sm:flex-row sm:items-center sm:justify-between">
                <a href="{{ route('dashboard', ['project' => $project->id]) }}" class="text-center text-sm font-medium text-gray-500 hover:text-gray-800">{{ __('Cancel') }}</a>

                <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center">
                    <p x-show="fileCount > maxUploads" x-cloak class="text-xs text-red-600">
                        {{ __('Up to :max files can be sent at once. Save first, then add the rest.', ['max' => $maxUploads]) }}
                    </p>
                    <button type="submit" name="action" value="draft" :disabled="fileCount > maxUploads"
                            class="rounded-md border border-lwua-dark bg-white px-6 py-2.5 text-sm font-semibold uppercase text-lwua-dark shadow-sm hover:bg-gray-50 disabled:opacity-50">
                        {{ __('Save') }}
                    </button>
                    <button type="submit" name="action" value="submit" :disabled="fileCount > maxUploads"
                            x-on:click="if (! confirm(@js(__('Send the post-evaluation documents to LWRD?')))) $event.preventDefault()"
                            class="rounded-md bg-lwua-dark px-8 py-2.5 text-sm font-semibold uppercase text-white shadow hover:brightness-110 disabled:opacity-50">
                        {{ __('Send to LWRD') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>

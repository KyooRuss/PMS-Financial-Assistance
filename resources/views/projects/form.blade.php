@php
    $type = $project->type;
    $maxUploads = (int) ini_get('max_file_uploads');
    $removed = collect(old('remove_documents', []))->map(fn ($id) => (int) $id);
    $documentsByRequirement = $project->exists ? $project->documents->groupBy('requirement_id') : collect();
    $returns = $project->exists ? $project->returnedItems->keyBy('requirement_id') : collect(); // open returns only (see edit())
    $isReturned = $project->status === \App\Enums\ProjectStatus::Returned;
@endphp

<x-app-layout>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- Heading --}}
        <div class="flex flex-wrap items-center gap-6">
            <div>
                <h1 class="text-2xl font-bold text-lwua-navy">{{ __('Request a Project') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ __('Upload your requirements.') }}</p>
            </div>

            <span class="rounded-full bg-white px-6 py-2 text-sm font-bold uppercase text-red-700 shadow ring-1 ring-black/10">
                {{ $type->isEmergency() ? __('Emergency') : $type->annex() }}
            </span>

            @if ($project->exists)
                <span class="text-sm text-gray-500">
                    <span class="font-semibold text-lwua-navy">{{ $project->reference }}</span> · {{ $project->status->label() }}
                </span>
            @endif
        </div>
        <p class="mt-2 text-sm text-gray-600">{{ $type->label() }}</p>

        @if ($isReturned && $returns->isNotEmpty())
            @php
                $returnLog = $returns->first()->log;
            @endphp
            <div role="status" class="mt-6 rounded-md bg-amber-50 px-5 py-4 text-sm text-amber-900 ring-1 ring-amber-200">
                <p class="font-semibold">{{ __(':office returned this request on :date.', ['office' => $returnLog->office, 'date' => $returnLog->created_at->format('M j, Y')]) }}</p>
                <p class="mt-1">{{ __('Upload a corrected file for each item marked "Returned" below, then click Resubmit.') }}</p>
                @if ($returnLog->remarks)
                    <p class="mt-2 rounded bg-white px-3 py-2 ring-1 ring-amber-200"><span class="font-semibold">{{ __('Remarks:') }}</span> {{ $returnLog->remarks }}</p>
                @endif
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" class="mt-6 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">
                {{ __('Please fix the highlighted items below.') }}
                @error('documents') <span class="block">{{ $message }}</span> @enderror
            </div>
        @endif

        <form method="POST"
              action="{{ $project->exists ? route('projects.update', $project) : route('projects.store') }}"
              enctype="multipart/form-data"
              x-data="{
                  purpose: @js(old('funding_purpose', $project->funding_purpose?->value)),
                  fileCount: 0,
                  maxUploads: {{ $maxUploads }},
                  countFiles() {
                      this.fileCount = [...$el.querySelectorAll('input[type=file]:not(:disabled)')]
                          .reduce((total, input) => total + input.files.length, 0);
                  },
                  applies(itemPurpose) { return ! itemPurpose || ! this.purpose || itemPurpose === this.purpose },
              }"
              x-on:change="countFiles()"
              class="mt-6 space-y-6">
            @csrf
            @if ($project->exists)
                @method('PUT')
            @else
                <input type="hidden" name="type" value="{{ $type->value }}">
            @endif

            {{-- Project details --}}
            <section class="rounded-lg bg-white p-6 shadow-md ring-1 ring-black/5">
                <h2 class="text-lg font-semibold text-lwua-navy">{{ __('Project Details') }}</h2>

                <div class="mt-4 grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-input-label for="title" :value="__('Project Title')" />
                        <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title', $project->title)" required maxlength="255" />
                        <x-input-error class="mt-2" :messages="$errors->get('title')" />
                    </div>

                    <div>
                        <x-input-label for="amount" :value="__('Loan Amount Requested (₱)')" />
                        <x-text-input id="amount" name="amount" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('amount', $project->amount)" />
                        <p class="mt-1 text-xs text-gray-500">
                            @switch($type)
                                @case(\App\Enums\ProjectType::RegularAbove50M) {{ __('Must be more than ₱50,000,000.') }} @break
                                @case(\App\Enums\ProjectType::Regular50MBelow) {{ __('Must not exceed ₱50,000,000.') }} @break
                                @default {{ __('Amount needed for the emergency.') }}
                            @endswitch
                        </p>
                        <x-input-error class="mt-2" :messages="$errors->get('amount')" />
                    </div>

                    @if ($type->isEmergency())
                        <fieldset>
                            <legend class="block font-medium text-sm text-gray-700">{{ __('Emergency Loan For') }}</legend>
                            <div class="mt-2 flex gap-6">
                                @foreach (\App\Enums\FundingPurpose::cases() as $purpose)
                                    <label class="inline-flex items-center gap-2 text-sm">
                                        <input type="radio" name="funding_purpose" value="{{ $purpose->value }}" x-model="purpose" class="text-lwua-blue focus:ring-lwua-blue">
                                        {{ $purpose === \App\Enums\FundingPurpose::Capex ? __('CAPEX (infrastructure)') : __('O&M (operational costs)') }}
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error class="mt-2" :messages="$errors->get('funding_purpose')" />
                        </fieldset>
                    @endif

                    <div class="sm:col-span-2">
                        <x-input-label for="description" :value="__('Project Description')" />
                        <textarea id="description" name="description" rows="3" maxlength="2000"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">{{ old('description', $project->description) }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('description')" />
                    </div>
                </div>
            </section>

            {{-- Documentary requirements --}}
            <section class="rounded-lg bg-white shadow-md ring-1 ring-black/5">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-lg font-semibold text-lwua-navy">{{ __('Documentary Requirements') }}</h2>
                    <p class="text-xs text-gray-500">
                        {{ \App\Enums\RequirementStage::PreEvaluation->label() }} ·
                        {{ __('PDF only, up to 10 MB per file. Select all files for an item at once.') }}
                    </p>
                </div>

                <ol class="divide-y divide-gray-100">
                    @foreach ($requirements as $requirement)
                        @php
                            $key = "documents.{$requirement->id}";
                            $existing = $documentsByRequirement->get($requirement->id, collect());
                            $return = $returns->get($requirement->id);
                        @endphp

                        <li x-data="{ chosen: [] }"
                            x-show="applies(@js($requirement->funding_purpose?->value))"
                            @class([
                                'px-6 py-4',
                                'bg-red-50/60' => $errors->has($key) || $errors->has($key.'.*'),
                                'bg-amber-50' => $return && ! ($errors->has($key) || $errors->has($key.'.*')),
                            ])>
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="text-sm text-gray-800">
                                    <p>
                                        <span class="font-semibold">{{ $requirement->number }}.</span>
                                        {{ $requirement->description }}
                                        @if ($requirement->is_optional)
                                            <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600">{{ __('If applicable') }}</span>
                                        @endif
                                        @if ($requirement->funding_purpose)
                                            <span class="ml-1 rounded bg-blue-50 px-1.5 py-0.5 text-[11px] text-lwua-blue">{{ __(':purpose only', ['purpose' => $requirement->funding_purpose->label()]) }}</span>
                                        @endif
                                        @if ($return)
                                            <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-medium text-amber-800">{{ __('Returned') }}</span>
                                        @endif
                                    </p>
                                    @if ($return)
                                        <p class="mt-1 text-amber-900"><span class="font-semibold">{{ __('To fix:') }}</span> {{ $return->remarks }}</p>
                                    @endif
                                    @if ($requirement->details)
                                        <ul class="mt-1 space-y-0.5 pl-5 text-gray-600">
                                            @foreach (explode("\n", $requirement->details) as $line)
                                                <li>{{ $line }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>

                                <label class="inline-flex shrink-0 cursor-pointer items-center gap-2 self-start rounded border border-gray-300 bg-white px-4 py-2 text-xs text-gray-600 shadow-sm hover:bg-gray-50 focus-within:ring-2 focus-within:ring-lwua-blue">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v12m0-12l-4 4m4-4l4 4"/></svg>
                                    {{ __('Upload File') }}
                                    <input type="file" name="documents[{{ $requirement->id }}][]" multiple accept="application/pdf,.pdf"
                                           class="sr-only"
                                           :disabled="! applies(@js($requirement->funding_purpose?->value))"
                                           x-on:change="chosen = [...$event.target.files].map(file => file.name)">
                                </label>
                            </div>

                            {{-- Files already saved in the draft --}}
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

                            {{-- Files picked but not yet saved --}}
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

            {{-- Actions --}}
            <div class="flex flex-col-reverse items-stretch gap-3 sm:flex-row sm:items-center sm:justify-between">
                <a href="{{ route('dashboard') }}" class="text-center text-sm font-medium text-gray-500 hover:text-gray-800">{{ __('Cancel') }}</a>

                <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center">
                    <p x-show="fileCount > maxUploads" x-cloak class="text-xs text-red-600">
                        {{ __('Up to :max files can be sent at once. Save as draft first, then add the rest.', ['max' => $maxUploads]) }}
                    </p>

                    <button type="submit" name="action" value="draft" formnovalidate
                            :disabled="fileCount > maxUploads"
                            class="rounded-md border border-lwua-dark bg-white px-6 py-2.5 text-sm font-semibold uppercase text-lwua-dark shadow-sm hover:bg-gray-50 disabled:opacity-50">
                        {{ $isReturned ? __('Save Changes') : __('Save as Draft') }}
                    </button>

                    <button type="submit" name="action" value="submit"
                            :disabled="fileCount > maxUploads"
                            x-on:click="if (! confirm(@js($isReturned
                                ? __('Resubmit this request to WSUDD with your corrections?')
                                : __('Submit this request to LWUA? You cannot edit it after submitting unless it is returned.')))) $event.preventDefault()"
                            class="rounded-md bg-lwua-dark px-8 py-2.5 text-sm font-semibold uppercase text-white shadow hover:brightness-110 disabled:opacity-50">
                        {{ $isReturned ? __('Resubmit') : __('Submit') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>

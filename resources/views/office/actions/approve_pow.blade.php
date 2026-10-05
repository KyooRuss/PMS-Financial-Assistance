{{-- Administrator – approve the LWUA-POW or return it to ES (regular step 7, emergency CAPEX step 5). --}}
@php($prepared = $project->logsForStep('prepare_pow')->first())

<form method="POST" action="{{ route('office.projects.pow.approve', $project) }}" class="mt-3 space-y-4">
    @csrf

    <div class="rounded-md bg-gray-50 p-4 text-sm ring-1 ring-gray-100">
        <p class="font-semibold text-gray-900">{{ __('LWUA-POW for approval') }}</p>
        @if ($prepared)
            <p class="mt-1 text-xs text-gray-500">{{ __('Prepared by :name, :date', ['name' => $prepared->user?->full_name, 'date' => $prepared->created_at->format('M j, Y')]) }}</p>
            @foreach ($prepared->documents as $file)
                <a href="{{ route('documents.show', $file) }}" target="_blank" rel="noopener" class="mt-2 block truncate text-lwua-blue underline">{{ $file->original_name }}</a>
            @endforeach
            @if ($prepared->remarks)
                <p class="mt-2 whitespace-pre-line text-gray-700">{{ $prepared->remarks }}</p>
            @endif
        @endif
    </div>

    <div>
        <x-input-label for="remarks" :value="__('Remarks (required when returning to ES)')" />
        <textarea id="remarks" name="remarks" rows="3" maxlength="3000"
                  class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">{{ old('remarks') }}</textarea>
        <x-input-error class="mt-1" :messages="$errors->get('remarks')" />
    </div>

    <div class="flex flex-col gap-2">
        <button type="submit" onclick="return confirm(@js(__('Approve the LWUA-POW of :ref?', ['ref' => $project->reference])))"
                class="rounded-md bg-green-600 px-4 py-2.5 text-sm font-semibold uppercase text-white shadow hover:bg-green-700">
            {{ __('Approve POW') }}
        </button>
        <button type="submit" formaction="{{ route('office.projects.pow.return', $project) }}"
                onclick="return confirm(@js(__('Return the LWUA-POW of :ref to ES for revision?', ['ref' => $project->reference])))"
                class="rounded-md border border-amber-600 bg-white px-4 py-2.5 text-sm font-semibold uppercase text-amber-700 hover:bg-amber-50">
            {{ __('Return to ES for Revision') }}
        </button>
    </div>
</form>

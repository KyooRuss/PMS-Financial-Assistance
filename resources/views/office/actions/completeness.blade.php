{{-- WSUDD – completeness check. Rendered inside the page-wide #action-form (the checklist flags are part of it). --}}
<p class="mt-3 text-sm text-gray-700">{{ __('Open every file. If everything is complete, endorse the request. Otherwise flag the items to fix on the left and return it to the water district.') }}</p>

<div class="mt-4">
    <x-input-label for="remarks" :value="__('Remarks (optional)')" />
    <textarea id="remarks" name="remarks" rows="3" maxlength="2000"
              class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">{{ old('remarks') }}</textarea>
    <x-input-error class="mt-1" :messages="$errors->get('remarks')" />
</div>

<div class="mt-4 flex flex-col gap-2">
    <button type="submit" formaction="{{ route('office.projects.endorse', $project) }}"
            onclick="return confirm(@js(__('Endorse :ref as complete?', ['ref' => $project->reference])))"
            class="rounded-md bg-green-600 px-4 py-2.5 text-sm font-semibold uppercase text-white shadow hover:bg-green-700">
        {{ $project->type->isEmergency() ? __('Endorse to CPIC') : __('Complete – Endorse') }}
    </button>
    <button type="submit"
            onclick="return confirm(@js(__('Return :ref to the water district with the flagged items?', ['ref' => $project->reference])))"
            class="rounded-md border border-amber-600 bg-white px-4 py-2.5 text-sm font-semibold uppercase text-amber-700 hover:bg-amber-50">
        {{ __('Return to Water District') }}
    </button>
</div>

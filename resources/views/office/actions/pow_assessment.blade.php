{{-- ES – rapid assessment of the WD-POW (regular step 3). --}}
<form method="POST" action="{{ route('office.projects.pow-assessment', $project) }}" enctype="multipart/form-data" class="mt-3 space-y-4">
    @csrf
    <p class="text-sm text-gray-700">{{ __('Review the WD-prepared Program of Work (item 5) and the technical items 12–16, then record your findings for LWRD and CPIC.') }}</p>

    <div>
        <x-input-label for="findings" :value="__('Findings')" />
        <textarea id="findings" name="findings" rows="5" maxlength="3000" required
                  placeholder="{{ __('Components, costing, technical soundness, concerns…') }}"
                  class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">{{ old('findings') }}</textarea>
        <x-input-error class="mt-1" :messages="$errors->get('findings')" />
    </div>

    <div>
        <x-input-label for="assessment" :value="__('Assessment report (PDF, optional)')" />
        <input id="assessment" name="assessment" type="file" accept="application/pdf,.pdf" class="mt-1 block w-full text-xs text-gray-600 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-xs">
        <x-input-error class="mt-1" :messages="$errors->get('assessment')" />
    </div>

    <button type="submit" onclick="return confirm(@js(__('Forward :ref to LWRD?', ['ref' => $project->reference])))"
            class="w-full rounded-md bg-lwua-dark px-4 py-2.5 text-sm font-semibold uppercase text-white shadow hover:brightness-110">
        {{ __('Forward to LWRD') }}
    </button>
</form>
